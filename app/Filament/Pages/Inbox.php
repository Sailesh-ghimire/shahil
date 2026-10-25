<?php

namespace App\Filament\Pages;

use App\Actions\Messaging\SendChannelMessage;
use App\Actions\Sales\AssignLeadOwner;
use App\Enums\Channel;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Filament\Forms\OwnerSelect;
use App\Models\Contact;
use App\Models\ContactMethod;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * One inbox for WhatsApp, Instagram and Facebook. Polls every 10 seconds (Reverb is not installed yet).
 */
class Inbox extends Page
{
    protected string $view = 'filament.pages.inbox';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Engage';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Inbox';

    #[Url]
    public ?int $conversation = null;

    #[Url]
    public string $show = 'all';

    #[Url]
    public ?string $channel = null;

    public string $search = '';

    public string $reply = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('inbox.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = Conversation::query()->where('unread_count', '>', 0)->where(fn (Builder $query) => $query->where('assignee_id', auth()->id())->orWhereNull('assignee_id'))->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public function mount(): void
    {
        if ($this->conversation !== null) {
            $this->open($this->conversation);
        }
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return Conversation::query()
            ->with(['channelAccount', 'contact', 'lead', 'assignee'])
            ->when($this->show === 'mine', fn (Builder $query) => $query->where('assignee_id', auth()->id()))
            ->when($this->show === 'unassigned', fn (Builder $query) => $query->whereNull('assignee_id'))
            ->when($this->show === 'unread', fn (Builder $query) => $query->where('unread_count', '>', 0))
            ->when(Channel::tryFrom((string) $this->channel), fn (Builder $query, Channel $channel) => $query->whereHas('channelAccount', fn (Builder $account) => $account->where('channel', $channel)))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('display_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('handle', 'like', '%'.trim($this->search).'%')
                ->orWhere('last_message_preview', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc('last_message_at')
            ->limit(60)
            ->get();
    }

    public function getActive(): ?Conversation
    {
        if ($this->conversation === null) {
            return null;
        }

        return Conversation::query()
            ->with(['channelAccount', 'contact', 'lead', 'assignee'])
            ->find($this->conversation);
    }

    /**
     * @return Collection<int, Message>
     */
    public function getThreadMessages(Conversation $conversation): Collection
    {
        return $conversation->messages()->with('sender')->reorder('id', 'desc')->limit(100)->get()->reverse()->values();
    }

    public function open(int $conversationId): void
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null) {
            $this->conversation = null;

            return;
        }

        $this->authorize('view', $conversation);
        $this->conversation = $conversation->getKey();
        $this->reply = '';

        if ($conversation->unread_count > 0 && ($conversation->assignee_id === null || $conversation->assignee_id === auth()->id())) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }
    }

    public function closeConversation(): void
    {
        $this->conversation = null;
    }

    public function send(): void
    {
        $conversation = $this->getActive();

        if ($conversation === null) {
            return;
        }

        $this->authorize('reply', $conversation);

        try {
            app(SendChannelMessage::class)->text($conversation, auth()->user(), $this->reply);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Message not sent')->body($exception->getMessage())->warning()->send();

            return;
        }

        $this->reply = '';
    }

    public function sendTemplateAction(): Action
    {
        return Action::make('sendTemplate')
            ->label('Send template')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalDescription('Meta charges for template messages, by country and category.')
            ->visible(fn (): bool => $this->getActive()?->channel() === Channel::WhatsApp && auth()->user()->can('reply', $this->getActive()))
            ->schema([
                Select::make('template_id')
                    ->label('Approved template')
                    ->options(fn (): array => WhatsAppTemplate::query()
                        ->where('channel_account_id', $this->getActive()?->channel_account_id)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (WhatsAppTemplate $template): array => [$template->id => "{$template->name} ({$template->language})"])
                        ->all())
                    ->helperText('No templates? Sync them from Settings → Channels.')
                    ->required()
                    ->live(),
                ...collect(range(1, 5))->map(fn (int $i): TextInput => TextInput::make("parameter_{$i}")
                    ->label("Value for {{{$i}}}")
                    ->visible(fn (Get $get): bool => (WhatsAppTemplate::query()->find($get('template_id'))->parameter_count ?? 0) >= $i)
                    ->required()
                    ->maxLength(500))->all(),
            ])
            ->action(function (array $data): void {
                $template = WhatsAppTemplate::query()->findOrFail($data['template_id']);
                $parameters = collect(range(1, $template->parameter_count))->map(fn (int $i): string => (string) ($data["parameter_{$i}"] ?? ''))->all();

                try {
                    app(SendChannelMessage::class)->template($this->getActive(), auth()->user(), $template, $parameters);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Template not sent')->body($exception->getMessage())->warning()->send();

                    return;
                }

                Notification::make()->title('Template queued')->success()->send();
            });
    }

    public function createLeadAction(): Action
    {
        return Action::make('createLead')
            ->label('Create lead')
            ->icon(Heroicon::OutlinedFunnel)
            ->visible(fn (): bool => ($active = $this->getActive()) !== null && $active->contact_id === null && $active->lead_id === null && auth()->user()->can('link', $active))
            ->fillForm(fn (): array => ['name' => $this->getActive()?->title()])
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('company_name')->label('Company')->maxLength(255),
            ])
            ->action(function (array $data): void {
                $conversation = $this->getActive();
                $isWhatsApp = $conversation->channel() === Channel::WhatsApp;

                $lead = Lead::query()->create([
                    'name' => $data['name'],
                    'company_name' => $data['company_name'] ?? null,
                    'phone' => $isWhatsApp ? $conversation->handle : null,
                    'handle' => $isWhatsApp ? null : $conversation->display_name,
                    'source' => $conversation->channel()->recordSource(),
                    'status' => LeadStatus::New,
                    'owner_id' => $conversation->assignee_id ?? auth()->id(),
                ]);

                app(AssignLeadOwner::class)->handle($lead);
                $conversation->forceFill(['lead_id' => $lead->getKey()])->save();

                Notification::make()->title('Lead created')->body(e($lead->name).' is linked to this conversation.')->success()->send();
            });
    }

    public function linkContactAction(): Action
    {
        return Action::make('linkContact')
            ->label('Link to contact')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (): bool => ($active = $this->getActive()) !== null && $active->contact_id === null && auth()->user()->can('link', $active))
            ->schema([
                Select::make('contact_id')
                    ->label('Contact')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Contact::query()
                        ->where(fn (Builder $query) => $query->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Contact $contact): array => [$contact->id => $contact->name])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $conversation = $this->getActive();
                $contact = Contact::query()->findOrFail($data['contact_id']);

                $conversation->forceFill(['contact_id' => $contact->getKey()])->save();

                if ($conversation->channel() === Channel::WhatsApp && filled($conversation->handle)) {
                    $exists = $contact->methods()->where('normalized_value', ContactMethod::normalizeValue($conversation->handle, ContactMethodType::WhatsApp))->exists();

                    if (! $exists) {
                        $contact->methods()->create(['type' => ContactMethodType::WhatsApp, 'value' => $conversation->handle]);
                    }
                }

                Notification::make()->title('Linked to '.e($contact->name))->success()->send();
            });
    }

    public function assignAction(): Action
    {
        return Action::make('assign')
            ->label('Assign')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->visible(fn (): bool => ($active = $this->getActive()) !== null && auth()->user()->can('assign', $active))
            ->fillForm(fn (): array => ['assignee_id' => $this->getActive()?->assignee_id])
            ->schema([
                OwnerSelect::make('assignee_id', 'Assign to')->default(null)->placeholder('Nobody'),
            ])
            ->action(function (array $data): void {
                $this->getActive()->forceFill(['assignee_id' => $data['assignee_id'] ?? null])->save();

                Notification::make()->title('Conversation assigned')->success()->send();
            });
    }

    public function assignToMeAction(): Action
    {
        return Action::make('assignToMe')
            ->label('Assign to me')
            ->icon(Heroicon::OutlinedHandRaised)
            ->visible(fn (): bool => ($active = $this->getActive()) !== null && $active->assignee_id === null && auth()->user()->can('claim', $active))
            ->action(function (): void {
                $this->getActive()->forceFill(['assignee_id' => auth()->id()])->save();

                Notification::make()->title('Assigned to you')->success()->send();
            });
    }
}

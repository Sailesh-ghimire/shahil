<?php

namespace App\Filament\Pages;

use App\Actions\Messaging\ConnectChannelAccount;
use App\Enums\Channel;
use App\Filament\Tables\ChannelAccountsTable;
use App\Models\ChannelAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/**
 * Settings → Channels: connect WhatsApp numbers, Facebook Pages and Instagram accounts.
 * Until the Meta app is approved for Embedded Signup, accounts are connected with their id and an access token.
 */
class Channels extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.channels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 25;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('channels.manage') ?? false;
    }

    public function table(Table $table): Table
    {
        return ChannelAccountsTable::configure($table);
    }

    /**
     * Webhook URLs and verify token to paste into the Meta app's webhook settings.
     *
     * @return array<string, string>
     */
    public function getWebhookUrls(): array
    {
        return collect(Channel::cases())
            ->mapWithKeys(fn (Channel $channel): array => [$channel->getLabel() => route('webhooks.meta.receive', $channel->value)])
            ->all();
    }

    public function isMetaConfigured(): bool
    {
        return filled(config('services.meta.app_secret')) && filled(config('services.meta.verify_token'));
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                static::connectAction(Channel::WhatsApp),
                static::connectAction(Channel::Facebook),
                static::connectAction(Channel::Instagram),
            ])
                ->label('Connect account')
                ->icon(Heroicon::OutlinedPlus)
                ->button(),
        ];
    }

    public static function connectAction(Channel $channel): Action
    {
        [$idLabel, $idHelp] = match ($channel) {
            Channel::WhatsApp => ['Phone number id', 'WhatsApp Manager → Phone numbers → the number’s id (not the phone number itself).'],
            Channel::Facebook => ['Page id', 'Facebook Page → About → Page transparency, or Meta Business Suite settings.'],
            Channel::Instagram => ['Instagram account id', 'The id of the Instagram professional account linked to your Page.'],
        };

        return Action::make('connect'.$channel->getLabel())
            ->label($channel->getLabel())
            ->modalHeading('Connect '.$channel->getLabel())
            ->modalDescription('Paste the account id and a long-lived access token from your Meta app. We check the token before saving.')
            ->schema(array_values(array_filter([
                TextInput::make('name')
                    ->label('Name in the CRM')
                    ->placeholder($channel === Channel::WhatsApp ? 'Acme Sales' : 'Acme Traders')
                    ->required()
                    ->maxLength(120),
                TextInput::make('external_id')
                    ->label($idLabel)
                    ->helperText($idHelp)
                    ->required()
                    ->regex('/^\d{5,25}$/')
                    ->validationMessages(['regex' => 'Meta ids are numbers only.']),
                TextInput::make('display_handle')
                    ->label($channel === Channel::WhatsApp ? 'Phone number shown to customers' : 'Page or account name')
                    ->placeholder($channel === Channel::WhatsApp ? '+977 980-0000000' : '@acmetraders')
                    ->maxLength(120),
                $channel === Channel::WhatsApp
                    ? TextInput::make('business_account_id')
                        ->label('WhatsApp Business Account id')
                        ->helperText('Needed to sync approved message templates.')
                        ->regex('/^\d{5,25}$/')
                    : null,
                Textarea::make('access_token')
                    ->label('Access token')
                    ->required()
                    ->rows(3),
            ])))
            ->action(function (array $data, Action $action) use ($channel): void {
                try {
                    $account = app(ConnectChannelAccount::class)->handle(currentTeam(), auth()->user(), $channel, $data);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($channel->getLabel().' not connected')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title($channel->getLabel().' connected')
                    ->body(e($account->name).' will now receive messages in the Inbox.')
                    ->success()
                    ->send();
            })
            ->authorize('create', ChannelAccount::class);
    }
}

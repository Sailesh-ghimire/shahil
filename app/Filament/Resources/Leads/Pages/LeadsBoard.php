<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Actions\Sales\ChangeLeadStatus;
use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Leads board: New, Contacted, Qualified, Unqualified. Drag a card to change status;
 * dropping on Unqualified asks for a reason. 25 cards per column, "load more" for the rest.
 */
class LeadsBoard extends Page
{
    public const PAGE_SIZE = 25;

    protected static string $resource = LeadResource::class;

    protected string $view = 'filament.resources.leads.board';

    protected static ?string $title = 'Leads board';

    #[Url]
    public bool $onlyMine = false;

    /**
     * Cards shown per column, keyed by status value.
     *
     * @var array<string, int>
     */
    public array $limits = [];

    public static function canAccess(array $parameters = []): bool
    {
        return LeadResource::canViewAny();
    }

    /**
     * @return array<string, array{status: LeadStatus, leads: Collection<int, Lead>, total: int, value: string}>
     */
    public function getColumns(): array
    {
        $columns = [];

        foreach (LeadStatus::boardColumns() as $status) {
            $query = $this->query()->where('status', $status);
            $limit = $this->limits[$status->value] ?? self::PAGE_SIZE;

            $columns[$status->value] = [
                'status' => $status,
                'leads' => (clone $query)->with('owner')->orderBy('board_position')->orderByDesc('id')->limit($limit)->get(),
                'total' => (clone $query)->count(),
                'value' => Money::short((int) (clone $query)->sum('estimated_value'), currentTeam()->currency),
            ];
        }

        return $columns;
    }

    /**
     * @return Builder<Lead>
     */
    protected function query(): Builder
    {
        return Lead::query()->when($this->onlyMine, fn (Builder $query) => $query->where('owner_id', auth()->id()));
    }

    public function loadMore(string $status): void
    {
        $this->limits[$status] = ($this->limits[$status] ?? self::PAGE_SIZE) + self::PAGE_SIZE;
    }

    /**
     * Called by wire:sort when a card is dropped.
     */
    public function moveLead(int|string $leadId, int $position, string $status): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $target = LeadStatus::from($status);

        if (! auth()->user()->can('update', $lead)) {
            Notification::make()->title('You can only move your own leads')->warning()->send();

            return;
        }

        if ($target === LeadStatus::Unqualified && $lead->status !== LeadStatus::Unqualified) {
            $this->mountAction('markUnqualified', ['lead' => $lead->getKey(), 'position' => $position]);

            return;
        }

        $this->changeStatus($lead, $target, null, $position);
    }

    public function markUnqualifiedAction(): Action
    {
        return Action::make('markUnqualified')
            ->modalHeading('Why is this lead unqualified?')
            ->modalWidth('md')
            ->schema([
                TextInput::make('reason')
                    ->required()
                    ->datalist(['Spam', 'No budget', 'Not a fit', 'Duplicate', 'No response']),
            ])
            ->action(function (array $data, array $arguments): void {
                $lead = Lead::query()->findOrFail($arguments['lead']);
                $this->authorize('update', $lead);

                $this->changeStatus($lead, LeadStatus::Unqualified, $data['reason'], $arguments['position'] ?? 0);
            });
    }

    protected function changeStatus(Lead $lead, LeadStatus $status, ?string $reason, ?int $position): void
    {
        $from = $lead->status;

        try {
            app(ChangeLeadStatus::class)->handle($lead, $status, $reason, $position);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Lead not moved')->body($exception->getMessage())->warning()->send();

            return;
        }

        if ($from === $status) {
            return;
        }

        Notification::make()
            ->title('Lead status changed')
            ->body(e($lead->name).' is now '.$status->getLabel().'.')
            ->success()
            ->actions([
                Action::make('undo')
                    ->label('Undo')
                    ->color('gray')
                    ->dispatch('undoLeadMove', [$lead->getKey(), $from->value])
                    ->close(),
            ])
            ->send();
    }

    #[On('undoLeadMove')]
    public function undoLeadMove(int $leadId, string $status): void
    {
        $lead = Lead::query()->findOrFail($leadId);

        $this->authorize('update', $lead);

        app(ChangeLeadStatus::class)->handle($lead, LeadStatus::from($status), $lead->unqualified_reason);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('List')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(LeadResource::getUrl()),
            CreateAction::make()
                ->label('New lead')
                ->url(LeadResource::getUrl('create')),
        ];
    }
}

<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Actions\Sales\MoveDealToStage;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Deals board: one column per stage with count and total value. Dropping on Won asks for the
 * close date; dropping on Lost asks for a reason. 25 cards per column, "load more" for the rest.
 */
class DealsBoard extends Page
{
    public const PAGE_SIZE = 25;

    protected static string $resource = DealResource::class;

    protected string $view = 'filament.resources.deals.board';

    protected static ?string $title = 'Deals board';

    #[Url]
    public ?int $pipelineId = null;

    #[Url]
    public bool $onlyMine = false;

    /**
     * @var array<int, int>
     */
    public array $limits = [];

    public static function canAccess(array $parameters = []): bool
    {
        return DealResource::canViewAny();
    }

    public function mount(): void
    {
        $this->pipelineId ??= Pipeline::defaultForCurrentWorkspace()?->getKey();
    }

    /**
     * @return array<int, string>
     */
    public function getPipelineOptions(): array
    {
        return Pipeline::query()->orderBy('sort')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, array{stage: Stage, deals: Collection<int, Deal>, total: int, value: string}>
     */
    public function getColumns(): array
    {
        $stages = Stage::query()->where('pipeline_id', $this->pipelineId)->orderBy('sort')->get();
        $currency = currentTeam()->currency;
        $columns = [];

        foreach ($stages as $stage) {
            $query = $this->query()->where('stage_id', $stage->getKey());
            $limit = $this->limits[$stage->getKey()] ?? self::PAGE_SIZE;

            $columns[$stage->getKey()] = [
                'stage' => $stage,
                'deals' => (clone $query)->with(['company', 'owner'])->orderBy('board_position')->orderByDesc('id')->limit($limit)->get(),
                'total' => (clone $query)->count(),
                'value' => Money::short((int) (clone $query)->sum('amount'), $currency),
            ];
        }

        return $columns;
    }

    /**
     * Closed deals older than 30 days drop off the board to keep it light.
     *
     * @return Builder<Deal>
     */
    protected function query(): Builder
    {
        return Deal::query()
            ->where(fn (Builder $query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', now()->subDays(30)))
            ->when($this->onlyMine, fn (Builder $query) => $query->where('owner_id', auth()->id()));
    }

    public function loadMore(int $stageId): void
    {
        $this->limits[$stageId] = ($this->limits[$stageId] ?? self::PAGE_SIZE) + self::PAGE_SIZE;
    }

    /**
     * Called by wire:sort when a card is dropped.
     */
    public function moveDeal(int|string $dealId, int $position, int|string $stageId): void
    {
        $deal = Deal::query()->with('stage')->findOrFail($dealId);
        $stage = Stage::query()->findOrFail($stageId);

        if (! auth()->user()->can('update', $deal)) {
            Notification::make()->title('You can only move your own deals')->warning()->send();

            return;
        }

        if ($stage->getKey() !== $deal->stage_id && $stage->isWon()) {
            $this->mountAction('markWon', ['deal' => $deal->getKey(), 'stage' => $stage->getKey(), 'position' => $position]);

            return;
        }

        if ($stage->getKey() !== $deal->stage_id && $stage->isLost()) {
            $this->mountAction('markLost', ['deal' => $deal->getKey(), 'stage' => $stage->getKey(), 'position' => $position]);

            return;
        }

        $this->move($deal, $stage, $position);
    }

    public function markWonAction(): Action
    {
        return Action::make('markWon')
            ->modalHeading('Deal won — when did it close?')
            ->modalWidth('md')
            ->schema([
                DatePicker::make('closed_at')->label('Close date')->default(now())->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->move(
                    $this->authorizedDeal($arguments['deal']),
                    Stage::query()->findOrFail($arguments['stage']),
                    $arguments['position'] ?? 0,
                    closedAt: Carbon::parse($data['closed_at']),
                );
            });
    }

    public function markLostAction(): Action
    {
        return Action::make('markLost')
            ->modalHeading('Why was the deal lost?')
            ->modalWidth('md')
            ->schema([
                TextInput::make('lost_reason')
                    ->label('Reason')
                    ->datalist(['Price too high', 'Chose a competitor', 'No budget', 'No decision', 'Timing'])
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->move(
                    $this->authorizedDeal($arguments['deal']),
                    Stage::query()->findOrFail($arguments['stage']),
                    $arguments['position'] ?? 0,
                    lostReason: $data['lost_reason'],
                );
            });
    }

    /**
     * Modal arguments come from the browser: re-check the user may change this deal.
     */
    protected function authorizedDeal(mixed $dealId): Deal
    {
        $deal = Deal::query()->with('stage')->findOrFail($dealId);

        $this->authorize('update', $deal);

        return $deal;
    }

    protected function move(Deal $deal, Stage $stage, int $position, ?Carbon $closedAt = null, ?string $lostReason = null): void
    {
        $fromStage = $deal->stage;

        try {
            app(MoveDealToStage::class)->handle($deal, $stage, $closedAt, $lostReason, $position);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Deal not moved')->body($exception->getMessage())->warning()->send();

            return;
        }

        if ($fromStage->getKey() === $stage->getKey()) {
            return;
        }

        $notification = Notification::make()
            ->title('Deal moved')
            ->body(e($deal->title).' is now in '.e($stage->name).'.')
            ->actions([
                Action::make('view')
                    ->label('View deal')
                    ->button()
                    ->url(DealResource::getUrl('view', ['record' => $deal])),
                Action::make('undo')
                    ->label('Undo')
                    ->color('gray')
                    ->dispatch('undoDealMove', [$deal->getKey(), $fromStage->getKey()])
                    ->close(),
            ]);

        ($stage->isLost() ? $notification->warning() : $notification->success())->send();
    }

    #[On('undoDealMove')]
    public function undoDealMove(int $dealId, int $stageId): void
    {
        $deal = Deal::query()->findOrFail($dealId);

        $this->authorize('update', $deal);

        $stage = Stage::query()->findOrFail($stageId);

        app(MoveDealToStage::class)->handle($deal, $stage, lostReason: $stage->isLost() ? ($deal->lost_reason ?? 'Undo') : null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('List')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(DealResource::getUrl()),
            CreateAction::make()
                ->label('New deal')
                ->url(DealResource::getUrl('create')),
        ];
    }
}

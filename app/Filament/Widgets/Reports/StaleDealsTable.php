<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Actions\AddFollowUpAction;
use App\Filament\Resources\Deals\DealResource;
use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use App\Models\Deal;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Open deals with no activity for 7+ days: what should we chase today?
 */
class StaleDealsTable extends TableWidget
{
    use UsesReportFilters;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $filters = $this->reportFilters();
        $cutoff = now()->subDays(Deal::STALE_AFTER_DAYS);

        return $table
            ->heading('Stale deals')
            ->description('Open deals with no activity for '.Deal::STALE_AFTER_DAYS.'+ days, biggest first.')
            ->query(fn (): Builder => $filters->forDeals(Deal::query())
                ->with(['company', 'owner', 'stage'])
                ->whereNull('closed_at')
                ->where(fn (Builder $query) => $query
                    ->where('last_activity_at', '<', $cutoff)
                    ->orWhere(fn (Builder $query) => $query->whereNull('last_activity_at')->where('stage_changed_at', '<', $cutoff))))
            ->defaultSort('amount', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Deal $record): string => DealResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nothing is going stale')
            ->columns([
                TextColumn::make('title')->weight('semibold')->description(fn (Deal $record): ?string => $record->company?->name),
                TextColumn::make('stage.name')->label('Stage')->badge()->color('gray'),
                TextColumn::make('amount')
                    ->label('Value')
                    ->formatStateUsing(fn (Deal $record): string => Money::format($record->amount, $record->currency))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('idle')
                    ->label('Idle')
                    ->state(fn (Deal $record): string => $record->daysIdle().' days')
                    ->color('danger'),
                TextColumn::make('owner.name')->label('Owner')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('addFollowUp')
                    ->label('Add follow-up')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->schema(fn (Deal $record): array => AddFollowUpAction::schema('Follow up: '.$record->title))
                    ->action(fn (Deal $record, array $data) => AddFollowUpAction::create($record, $data)),
            ]);
    }
}

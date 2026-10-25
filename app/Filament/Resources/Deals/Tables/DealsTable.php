<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Enums\StageKind;
use App\Filament\Exports\DealExporter;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Tables\BulkAssignOwnerAction;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['stage', 'company', 'owner']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->weight('semibold')
                    ->description(fn (Deal $record): ?string => $record->company?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stage.name')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (Deal $record): string => $record->stage->kind->getColor()),
                TextColumn::make('amount')
                    ->label('Value')
                    ->formatStateUsing(fn (Deal $record): string => Money::format($record->amount, $record->currency))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned'),
                TextColumn::make('expected_close_on')
                    ->label('Expected close')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('idle')
                    ->label('Idle')
                    ->state(fn (Deal $record): string => $record->closed_at ? '—' : $record->daysIdle().' d')
                    ->color(fn (Deal $record): string => $record->isStale() ? 'danger' : 'gray'),
            ])
            ->filters([
                Filter::make('mine')
                    ->label('My deals')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('owner_id', auth()->id())),
                SelectFilter::make('state')
                    ->label('Status')
                    ->options(['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $state) => $query->whereHas('stage', fn (Builder $stage) => $stage->where('kind', StageKind::from($state))),
                    )),
                SelectFilter::make('pipeline_id')
                    ->label('Pipeline')
                    ->options(fn (): array => Pipeline::query()->orderBy('sort')->pluck('name', 'id')->all()),
                SelectFilter::make('stage_id')
                    ->label('Stage')
                    ->options(fn (): array => Stage::query()->with('pipeline')->orderBy('pipeline_id')->orderBy('sort')->get()->mapWithKeys(fn (Stage $stage): array => [$stage->id => $stage->pipeline->name.' · '.$stage->name])->all()),
                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => OwnerSelect::options()),
                Filter::make('stale')
                    ->label('No activity for 7+ days')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('closed_at')->where(fn (Builder $query) => $query
                        ->where('last_activity_at', '<', now()->subDays(Deal::STALE_AFTER_DAYS))
                        ->orWhere(fn (Builder $query) => $query->whereNull('last_activity_at')->where('stage_changed_at', '<', now()->subDays(Deal::STALE_AFTER_DAYS))))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAssignOwnerAction::make(),
                    ExportBulkAction::make()
                        ->exporter(DealExporter::class)
                        ->authorize('export', Deal::class),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

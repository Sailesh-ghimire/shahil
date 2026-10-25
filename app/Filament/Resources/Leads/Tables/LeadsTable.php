<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Enums\RecordSource;
use App\Filament\Exports\LeadExporter;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Resources\Leads\Actions\ConvertLeadAction;
use App\Filament\Tables\BulkAssignOwnerAction;
use App\Models\Lead;
use App\Support\Money;
use Filament\Actions\ActionGroup;
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

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owner'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Lead $record): ?string => $record->company_name)
                    ->searchable(['name', 'company_name', 'email', 'phone', 'handle'])
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('source')
                    ->badge(),
                TextColumn::make('estimated_value')
                    ->label('Est. value')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? Money::format($state, currentTeam()->currency) : '—')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('mine')
                    ->label('My leads')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('owner_id', auth()->id())),
                SelectFilter::make('status')
                    ->options(LeadStatus::class)
                    ->multiple(),
                SelectFilter::make('source')
                    ->options(RecordSource::class)
                    ->multiple(),
                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => OwnerSelect::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ConvertLeadAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAssignOwnerAction::make(),
                    ExportBulkAction::make()
                        ->exporter(LeadExporter::class)
                        ->authorize('export', Lead::class),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

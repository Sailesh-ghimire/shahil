<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanySize;
use App\Filament\Exports\CompanyExporter;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Tables\BulkAssignOwnerAction;
use App\Models\Company;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'tags'])->withCount('contacts'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Company $record): ?string => $record->website)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('industry')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('contacts_count')
                    ->label('People')
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => OwnerSelect::options()),
                SelectFilter::make('industry')
                    ->options(fn (): array => Company::query()->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry', 'industry')->all()),
                SelectFilter::make('size')
                    ->options(CompanySize::class),
                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),
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
                        ->exporter(CompanyExporter::class)
                        ->authorize('export', Company::class),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

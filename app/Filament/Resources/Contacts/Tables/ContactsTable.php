<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\RecordSource;
use App\Filament\Exports\ContactExporter;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Tables\BulkAssignOwnerAction;
use App\Models\Contact;
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

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['methods', 'companies', 'owner', 'tags']))
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->weight('semibold')
                    ->description(fn (Contact $record): ?string => $record->primaryEmail())
                    ->searchable(query: fn (Builder $query, string $search): Builder => Contact::scopeSearchPrefix($query, $search))
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('phone')
                    ->state(fn (Contact $record): ?string => $record->primaryPhone())
                    ->placeholder('—'),
                TextColumn::make('companies.name')
                    ->label('Companies')
                    ->badge()
                    ->color('gray')
                    ->limitList(2)
                    ->toggleable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('source')
                    ->badge()
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
                Filter::make('mine')
                    ->label('My contacts')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('owner_id', auth()->id())),
                SelectFilter::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => OwnerSelect::options()),
                SelectFilter::make('source')
                    ->options(RecordSource::class),
                SelectFilter::make('companies')
                    ->relationship('companies', 'name')
                    ->searchable()
                    ->preload(),
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
                        ->exporter(ContactExporter::class)
                        ->authorize('export', Contact::class),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

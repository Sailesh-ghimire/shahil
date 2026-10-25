<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Exports\ProductExporter;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Product $record): string => $record->sku)
                    ->searchable(['name', 'sku'])
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label('Unit price')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, currentTeam()->currency))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('tax_rate')
                    ->label('Tax')
                    ->suffix('%')
                    ->alignEnd(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => ! auth()->user()->can('products.manage'))
                    ->afterStateUpdated(function (Product $record, bool $state): void {
                        Notification::make()
                            ->title($state ? 'Product activated' : 'Product deactivated')
                            ->body(e($record->name).($state ? ' can be added to deals again.' : ' can no longer be added to new deals.'))
                            ->success()
                            ->actions([
                                Action::make('undo')
                                    ->label('Undo')
                                    ->color('gray')
                                    ->dispatch('undoProductToggle', [$record->getKey(), ! $state])
                                    ->close(),
                            ])
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),
                TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ProductExporter::class)
                        ->authorize('export', Product::class),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCube);
    }
}

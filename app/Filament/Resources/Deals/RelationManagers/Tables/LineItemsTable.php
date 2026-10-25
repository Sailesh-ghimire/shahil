<?php

namespace App\Filament\Resources\Deals\RelationManagers\Tables;

use App\Models\Deal;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineItemsTable
{
    public static function configure(Table $table): Table
    {
        $refreshDeal = function (RelationManager $livewire): void {
            /** @var Deal $deal */
            $deal = $livewire->getOwnerRecord();
            $deal->refresh();

            $livewire->dispatch('deal-lines-changed');

            Notification::make()
                ->title('Deal value updated')
                ->body(e($deal->title).' is now '.Money::format($deal->amount, $deal->currency).'.')
                ->success()
                ->send();
        };

        $canEditDeal = fn (RelationManager $livewire): bool => auth()->user()?->can('update', $livewire->getOwnerRecord()) ?? false;

        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort', $canEditDeal)
            ->paginated(false)
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Add products from the catalogue; the deal value becomes their total.')
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('quantity')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('unit_price')
                    ->label('Unit price')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, currentTeam()->currency, true))
                    ->alignEnd(),
                TextColumn::make('discount')->suffix('%')->alignEnd(),
                TextColumn::make('tax_rate')->label('Tax')->suffix('%')->alignEnd(),
                TextColumn::make('line_total')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, currentTeam()->currency, true))
                    ->weight('semibold')
                    ->alignEnd()
                    ->summarize(Sum::make()
                        ->label('Deal value')
                        ->formatStateUsing(fn (mixed $state): string => Money::format((int) $state, currentTeam()->currency, true))),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add product')
                    ->modalHeading('Add product')
                    ->authorize($canEditDeal)
                    ->mutateDataUsing(fn (array $data, RelationManager $livewire): array => [...$data, 'team_id' => $livewire->getOwnerRecord()->getAttribute('team_id')])
                    ->after($refreshDeal)
                    ->successNotification(null),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize($canEditDeal)
                    ->after($refreshDeal)
                    ->successNotification(null),
                DeleteAction::make()
                    ->authorize($canEditDeal)
                    ->after($refreshDeal)
                    ->successNotification(null),
            ])
            ->defaultSort('sort');
    }
}

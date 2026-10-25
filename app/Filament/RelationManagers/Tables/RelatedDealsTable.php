<?php

namespace App\Filament\RelationManagers\Tables;

use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RelatedDealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['stage', 'owner']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No deals yet')
            ->recordUrl(fn (Deal $record): string => DealResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->weight('semibold'),
                TextColumn::make('stage.name')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (Deal $record): string => $record->stage->kind->getColor()),
                TextColumn::make('amount')
                    ->label('Value')
                    ->formatStateUsing(fn (Deal $record): string => Money::format($record->amount, $record->currency))
                    ->alignEnd(),
                TextColumn::make('owner.name')->label('Owner')->placeholder('—'),
                TextColumn::make('expected_close_on')->label('Expected close')->date()->placeholder('—'),
            ]);
    }
}

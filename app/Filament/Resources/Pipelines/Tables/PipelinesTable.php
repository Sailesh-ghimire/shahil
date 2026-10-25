<?php

namespace App\Filament\Resources\Pipelines\Tables;

use App\Models\Pipeline;
use App\Models\Stage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PipelinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stages')->withCount('deals'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('stages')
                    ->state(fn (Pipeline $record): array => $record->stages->map(fn (Stage $stage): string => $stage->name)->all())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('deals_count')->label('Deals'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\RelationManagers\Tables;

use App\Support\Audit\AuditChanges;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistoryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading('No changes recorded')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i'),
                TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('System'),
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state)),
                TextColumn::make('changes')
                    ->label('What changed')
                    ->state(fn ($record): string => AuditChanges::summarize($record))
                    ->wrap(),
            ]);
    }
}

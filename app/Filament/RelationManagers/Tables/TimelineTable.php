<?php

namespace App\Filament\RelationManagers\Tables;

use App\Enums\ActivityType;
use App\Filament\Actions\LogActivityAction;
use App\Models\Activity;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimelineTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('happened_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Nothing logged yet')
            ->emptyStateDescription('Calls, meetings, notes and messages show up here, newest first.')
            ->columns([
                Split::make([
                    TextColumn::make('type')
                        ->badge()
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('title')
                            ->weight('semibold')
                            ->placeholder(fn (Activity $record): string => $record->type->getLabel()),
                        TextColumn::make('body')
                            ->color('gray')
                            ->wrap()
                            ->limit(400),
                        TextColumn::make('outcome')
                            ->prefix('Outcome: ')
                            ->color('gray')
                            ->size('sm'),
                    ]),
                    Stack::make([
                        TextColumn::make('happened_at')
                            ->dateTime('j M Y, H:i')
                            ->alignEnd(),
                        TextColumn::make('user.name')
                            ->color('gray')
                            ->size('sm')
                            ->alignEnd(),
                    ])->grow(false),
                ]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ActivityType::class),
            ])
            ->headerActions([
                LogActivityAction::make()
                    ->record(fn ($livewire) => $livewire->getOwnerRecord()),
            ]);
    }
}

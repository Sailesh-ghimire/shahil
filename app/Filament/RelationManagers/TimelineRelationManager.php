<?php

namespace App\Filament\RelationManagers;

use App\Filament\RelationManagers\Tables\TimelineTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * The selling timeline of any record that uses HasTimeline.
 */
class TimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Timeline';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return TimelineTable::configure($table);
    }
}

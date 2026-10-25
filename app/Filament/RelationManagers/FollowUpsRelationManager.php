<?php

namespace App\Filament\RelationManagers;

use App\Filament\RelationManagers\Tables\FollowUpsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * The "Tasks" tab on a contact, company, lead or deal.
 */
class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return FollowUpsTable::configure($table);
    }
}

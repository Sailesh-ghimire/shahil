<?php

namespace App\Filament\RelationManagers;

use App\Filament\RelationManagers\Tables\HistoryTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The record's audit entries ("History" tab). Admins and owners only.
 */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $title = 'History';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('audit.view') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return HistoryTable::configure($table);
    }
}

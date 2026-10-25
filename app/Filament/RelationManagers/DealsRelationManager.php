<?php

namespace App\Filament\RelationManagers;

use App\Filament\RelationManagers\Tables\RelatedDealsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * The "Deals" tab on a company or contact.
 */
class DealsRelationManager extends RelationManager
{
    protected static string $relationship = 'deals';

    protected static ?string $title = 'Deals';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return RelatedDealsTable::configure($table);
    }
}

<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Filament\Resources\Deals\RelationManagers\Schemas\LineItemForm;
use App\Filament\Resources\Deals\RelationManagers\Tables\LineItemsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Products on the deal. The deal value is the sum of the line totals.
 */
class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';

    protected static ?string $title = 'Products';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return LineItemForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return LineItemsTable::configure($table);
    }
}

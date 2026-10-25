<?php

namespace App\Filament\Exports;

use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sku')->label('SKU'),
            ExportColumn::make('name'),
            ExportColumn::make('category.name')->label('Category'),
            ExportColumn::make('unit_price')
                ->label('Unit price')
                ->formatStateUsing(fn (int $state): float => Money::fromMinor($state)),
            ExportColumn::make('tax_rate')->label('Tax rate'),
            ExportColumn::make('is_active')
                ->label('Active')
                ->formatStateUsing(fn (bool $state): string => $state ? 'yes' : 'no'),
            ExportColumn::make('description')->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your products export is ready: '.Number::format($export->successful_rows).' rows.';
    }
}

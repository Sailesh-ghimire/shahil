<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\HandlesDuplicates;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Money;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

/**
 * Duplicates are matched by SKU within the workspace. Prices are in the workspace currency (rupees, not paisa).
 *
 * @property Product|null $record
 */
class ProductImporter extends Importer
{
    use HandlesDuplicates;

    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU')
                ->guess(['sku', 'code', 'item code', 'product code'])
                ->requiredMapping()
                ->rules(['required', 'max:60']),
            ImportColumn::make('name')
                ->guess(['name', 'product', 'product name', 'item'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->rules(['nullable', 'max:80'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('unit_price')
                ->label('Unit price')
                ->guess(['price', 'unit price', 'rate', 'mrp'])
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->castStateUsing(fn (mixed $state): int => Money::toMinor($state)),
            ImportColumn::make('tax_rate')
                ->label('Tax rate')
                ->guess(['tax', 'vat', 'tax rate'])
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0', 'max:100']),
            ImportColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('description')
                ->rules(['nullable', 'max:5000']),
        ];
    }

    public function resolveRecord(): ?Product
    {
        $existing = Product::query()->where('sku', trim((string) $this->data['sku']))->first();

        return $this->resolveWithDuplicateRule($existing, fn (): Product => new Product);
    }

    protected function beforeSave(): void
    {
        $this->record->tax_rate ??= (string) Product::DEFAULT_TAX_RATE;
        $this->record->is_active ??= true;

        if (filled($this->data['category'] ?? null)) {
            $this->record->category_id = ProductCategory::query()->firstOrCreate([
                'team_id' => $this->record->team_id ?? currentTeam()->getKey(),
                'name' => trim($this->data['category']),
            ])->getKey();
        }
    }
}

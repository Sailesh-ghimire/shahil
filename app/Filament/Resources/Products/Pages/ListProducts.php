<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')
                ->label('Categories')
                ->color('gray')
                ->url(ProductCategoryResource::getUrl())
                ->visible(fn (): bool => ProductCategoryResource::canViewAny()),
            ActionGroup::make([
                SpreadsheetImportAction::make()
                    ->importer(ProductImporter::class)
                    ->authorize('import', Product::class),
                ExportAction::make()
                    ->exporter(ProductExporter::class)
                    ->authorize('export', Product::class),
            ])->button()->color('gray')->label('Import / export'),
            CreateAction::make()->label('New product'),
        ];
    }

    #[On('undoProductToggle')]
    public function undoProductToggle(int $productId, bool $isActive): void
    {
        $product = Product::query()->findOrFail($productId);

        $this->authorize('update', $product);

        $product->update(['is_active' => $isActive]);
    }
}

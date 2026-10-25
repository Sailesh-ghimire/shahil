<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected static ?string $title = 'Product categories';

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl() => 'Products',
            'Categories',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New category'),
        ];
    }
}

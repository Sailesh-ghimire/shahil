<?php

namespace App\Filament\Resources\Deals\RelationManagers\Schemas;

use App\Filament\Forms\MoneyInput;
use App\Models\DealProduct;
use App\Models\Product;
use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LineItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->options(fn (): array => Product::query()->where('is_active', true)->orderBy('name')->get()
                        ->mapWithKeys(fn (Product $product): array => [$product->id => $product->name.' ('.$product->sku.')'])->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $product = Product::query()->find($state);

                        if ($product === null) {
                            return;
                        }

                        $set('name', $product->name);
                        $set('unit_price', Money::fromMinor($product->unit_price));
                        $set('tax_rate', $product->tax_rate);
                    })
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0.01)
                    ->default(1)
                    ->required()
                    ->live(onBlur: true),
                MoneyInput::make('unit_price')
                    ->label('Unit price')
                    ->required()
                    ->live(onBlur: true),
                TextInput::make('discount')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->suffix('%')
                    ->live(onBlur: true),
                TextInput::make('tax_rate')
                    ->label('Tax rate')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(Product::DEFAULT_TAX_RATE)
                    ->suffix('%'),
                Text::make(fn (Get $get): string => 'Line total: '.Money::format(
                    DealProduct::calculateTotal((float) $get('quantity'), Money::toMinor($get('unit_price')), (float) $get('discount')),
                    currentTeam()->currency,
                    withDecimals: true,
                ).' before tax')
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Forms\MoneyInput;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(60)
                            ->unique(
                                table: 'products',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('team_id', Filament::getTenant()?->getKey()),
                            ),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(80),
                            ])
                            ->createOptionUsing(fn (array $data): int => ProductCategory::query()->firstOrCreate([
                                'team_id' => currentTeam()->getKey(),
                                'name' => trim($data['name']),
                            ])->getKey()),
                        MoneyInput::make('unit_price')
                            ->label('Unit price')
                            ->required(),
                        TextInput::make('tax_rate')
                            ->label('Tax rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(Product::DEFAULT_TAX_RATE)
                            ->helperText('13% VAT by default.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive products can’t be added to new deals.')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanySize;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Forms\TagsSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('industry')
                            ->maxLength(255)
                            ->datalist(['Agriculture', 'Construction', 'Education', 'FMCG', 'Healthcare', 'Hospitality', 'IT services', 'Logistics', 'Manufacturing', 'Retail', 'Travel']),
                        TextInput::make('website')
                            ->url()
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('pan_number')
                            ->label('PAN / tax number')
                            ->maxLength(50),
                        Select::make('size')
                            ->options(CompanySize::class),
                        OwnerSelect::make(),
                        TagsSelect::make(),
                        Textarea::make('address')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

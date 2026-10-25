<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStatus;
use App\Enums\RecordSource;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\OwnerSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lead')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_name')
                            ->label('Company')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('handle')
                            ->label('Social handle')
                            ->placeholder('@handle')
                            ->maxLength(255),
                        Select::make('source')
                            ->options(RecordSource::class)
                            ->default(RecordSource::Manual->value)
                            ->required(),
                        Select::make('status')
                            ->options(collect(LeadStatus::boardColumns())->mapWithKeys(fn (LeadStatus $status): array => [$status->value => $status->getLabel()])->all())
                            ->default(LeadStatus::New->value)
                            ->required()
                            ->live(),
                        TextInput::make('unqualified_reason')
                            ->label('Why unqualified')
                            ->visible(fn (Get $get): bool => in_array($get('status'), [LeadStatus::Unqualified, LeadStatus::Unqualified->value], true))
                            ->required(fn (Get $get): bool => in_array($get('status'), [LeadStatus::Unqualified, LeadStatus::Unqualified->value], true))
                            ->datalist(['Spam', 'No budget', 'Not a fit', 'Duplicate', 'No response']),
                        MoneyInput::make('estimated_value')
                            ->label('Estimated value'),
                        OwnerSelect::make(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

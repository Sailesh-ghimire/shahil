<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactMethodType;
use App\Enums\RecordSource;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Forms\TagsSelect;
use App\Models\Company;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Person')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->maxLength(255),
                        OwnerSelect::make(),
                        Select::make('source')
                            ->options(RecordSource::class)
                            ->default(RecordSource::Manual->value),
                        TagsSelect::make()->columnSpanFull(),
                    ]),
                Section::make('Emails, phones and handles')
                    ->description('Add as many as needed. Phone numbers are saved in international format so WhatsApp messages match.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('methods')
                            ->hiddenLabel()
                            ->relationship('methods')
                            ->table([
                                Repeater\TableColumn::make('Type')->width('160px'),
                                Repeater\TableColumn::make('Value'),
                                Repeater\TableColumn::make('Label')->width('160px'),
                                Repeater\TableColumn::make('Primary')->width('90px')->alignCenter(),
                            ])
                            ->schema([
                                Select::make('type')
                                    ->options(ContactMethodType::class)
                                    ->default(ContactMethodType::Email->value)
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(255)
                                    ->email(fn (Get $get): bool => $get('type') === ContactMethodType::Email->value || $get('type') === ContactMethodType::Email)
                                    ->placeholder(fn (Get $get): string => match ($get('type') instanceof ContactMethodType ? $get('type')->value : $get('type')) {
                                        'email' => 'name@company.com',
                                        'phone', 'whatsapp' => '+977 980-0000000',
                                        default => '@handle',
                                    }),
                                TextInput::make('label')
                                    ->placeholder('Work')
                                    ->maxLength(60),
                                Toggle::make('is_primary'),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add email, phone or handle'),
                    ]),
                Section::make('Companies')
                    ->description('A person can work at more than one company, with a job title at each.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('companyLinks')
                            ->hiddenLabel()
                            ->relationship('companyLinks')
                            ->table([
                                Repeater\TableColumn::make('Company'),
                                Repeater\TableColumn::make('Job title'),
                                Repeater\TableColumn::make('Main')->width('90px')->alignCenter(),
                            ])
                            ->schema([
                                Select::make('company_id')
                                    ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->createOptionForm([
                                        TextInput::make('name')->required()->maxLength(255),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => Company::query()->create([
                                        'name' => $data['name'],
                                        'owner_id' => auth()->id(),
                                    ])->getKey()),
                                TextInput::make('job_title')
                                    ->maxLength(255),
                                Toggle::make('is_primary'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add company'),
                    ]),
                Section::make('Notes')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')->hiddenLabel()->rows(3),
                    ]),
            ]);
    }
}

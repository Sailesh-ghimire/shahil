<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\OwnerSelect;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deal')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->createOptionForm([
                                TextInput::make('name')->required()->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): int => Company::query()->create([
                                'name' => $data['name'],
                                'owner_id' => auth()->id(),
                            ])->getKey()),
                        Select::make('contact_id')
                            ->label('Primary contact')
                            ->options(fn (Get $get): array => static::contactOptions($get('company_id')))
                            ->searchable(),
                        Select::make('pipeline_id')
                            ->label('Pipeline')
                            ->options(fn (): array => Pipeline::query()->orderBy('sort')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Pipeline::defaultForCurrentWorkspace()?->getKey())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('stage_id', Pipeline::query()->find($state)?->firstOpenStage()?->getKey())),
                        Select::make('stage_id')
                            ->label('Stage')
                            ->options(fn (Get $get): array => Stage::query()->where('pipeline_id', $get('pipeline_id'))->orderBy('sort')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Pipeline::defaultForCurrentWorkspace()?->firstOpenStage()?->getKey())
                            ->required()
                            ->live(),
                        TextInput::make('lost_reason')
                            ->label('Why lost')
                            ->datalist(['Price too high', 'Chose a competitor', 'No budget', 'No decision', 'Timing'])
                            ->visible(fn (Get $get): bool => (bool) Stage::query()->find($get('stage_id'))?->isLost())
                            ->required(fn (Get $get): bool => (bool) Stage::query()->find($get('stage_id'))?->isLost()),
                        MoneyInput::make('amount')
                            ->label('Value')
                            ->disabled(fn (?Deal $record): bool => $record?->lineItems()->exists() ?? false)
                            ->helperText(fn (?Deal $record): string => $record?->lineItems()->exists() ? 'Calculated from the line items.' : 'Add products below to calculate it automatically.'),
                        DatePicker::make('expected_close_on')
                            ->label('Expected close'),
                        OwnerSelect::make(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * People at the chosen company first; everyone when no company is chosen.
     *
     * @return array<int, string>
     */
    public static function contactOptions(mixed $companyId): array
    {
        $query = Contact::query()->orderBy('first_name');

        if (filled($companyId)) {
            $query->whereHas('companies', fn ($companies) => $companies->whereKey($companyId));
        }

        return $query->limit(200)->get()->mapWithKeys(fn (Contact $contact): array => [$contact->id => $contact->name])->all();
    }
}

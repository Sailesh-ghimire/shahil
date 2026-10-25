<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Lead;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Converted')
                    ->description('This lead was converted and is now read-only.')
                    ->visible(fn (Lead $record): bool => $record->isConverted())
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('convertedContact.name')
                            ->label('Contact')
                            ->url(fn (Lead $record): ?string => $record->converted_contact_id ? ContactResource::getUrl('view', ['record' => $record->converted_contact_id]) : null),
                        TextEntry::make('convertedCompany.name')
                            ->label('Company')
                            ->placeholder('—')
                            ->url(fn (Lead $record): ?string => $record->converted_company_id ? CompanyResource::getUrl('view', ['record' => $record->converted_company_id]) : null),
                        TextEntry::make('convertedDeal.title')
                            ->label('Deal')
                            ->url(fn (Lead $record): ?string => $record->converted_deal_id ? DealResource::getUrl('view', ['record' => $record->converted_deal_id]) : null),
                    ]),
                Section::make('Lead')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('source')->badge(),
                        TextEntry::make('estimated_value')
                            ->label('Estimated value')
                            ->formatStateUsing(fn (int $state): string => Money::format($state, currentTeam()->currency)),
                        TextEntry::make('company_name')->label('Company')->placeholder('—'),
                        TextEntry::make('email')->placeholder('—')->copyable(),
                        TextEntry::make('phone')->placeholder('—')->copyable(),
                        TextEntry::make('handle')->label('Social handle')->placeholder('—'),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                        TextEntry::make('contact.name')
                            ->label('Matches contact')
                            ->placeholder('No existing contact')
                            ->url(fn (Lead $record): ?string => $record->contact_id ? ContactResource::getUrl('view', ['record' => $record->contact_id]) : null),
                        TextEntry::make('unqualified_reason')->label('Why unqualified')->visible(fn (Lead $record): bool => filled($record->unqualified_reason)),
                        TextEntry::make('created_at')->label('Received')->since(),
                        TextEntry::make('last_activity_at')->label('Last activity')->since()->placeholder('Never'),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}

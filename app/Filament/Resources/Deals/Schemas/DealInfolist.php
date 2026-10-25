<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Deal;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DealInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Value')
                            ->formatStateUsing(fn (Deal $record): string => Money::format($record->amount, $record->currency))
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('stage.name')
                            ->label('Stage')
                            ->badge()
                            ->color(fn (Deal $record): string => $record->stage->kind->getColor())
                            ->helperText(fn (Deal $record): string => $record->stage->probability.'% win probability'),
                        TextEntry::make('pipeline.name')->label('Pipeline'),
                        TextEntry::make('company.name')
                            ->label('Company')
                            ->placeholder('—')
                            ->url(fn (Deal $record): ?string => $record->company_id ? CompanyResource::getUrl('view', ['record' => $record->company_id]) : null),
                        TextEntry::make('contact.name')
                            ->label('Primary contact')
                            ->placeholder('—')
                            ->url(fn (Deal $record): ?string => $record->contact_id ? ContactResource::getUrl('view', ['record' => $record->contact_id]) : null),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                        TextEntry::make('expected_close_on')->label('Expected close')->date()->placeholder('—'),
                        TextEntry::make('closed_at')->label('Closed')->date()->placeholder('Open'),
                        TextEntry::make('idle')
                            ->label('Last activity')
                            ->state(fn (Deal $record): string => $record->daysIdle() === 0 ? 'Today' : $record->daysIdle().' days ago')
                            ->color(fn (Deal $record): string => $record->isStale() ? 'danger' : 'gray'),
                        TextEntry::make('lost_reason')->label('Why lost')->visible(fn (Deal $record): bool => filled($record->lost_reason)),
                        TextEntry::make('lead.name')
                            ->label('From lead')
                            ->visible(fn (Deal $record): bool => $record->lead_id !== null)
                            ->url(fn (Deal $record): ?string => $record->lead_id ? LeadResource::getUrl('view', ['record' => $record->lead_id]) : null),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}

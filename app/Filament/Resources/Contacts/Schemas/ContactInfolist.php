<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactMethodType;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\CompanyContact;
use App\Models\Contact;
use App\Models\ContactMethod;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('emails')
                            ->state(fn (Contact $record): array => static::values($record, ContactMethodType::Email))
                            ->listWithLineBreaks()
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('phones')
                            ->state(fn (Contact $record): array => [
                                ...static::values($record, ContactMethodType::Phone),
                                ...array_map(fn (string $value): string => $value.' (WhatsApp)', static::values($record, ContactMethodType::WhatsApp)),
                            ])
                            ->listWithLineBreaks()
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('handles')
                            ->label('Social')
                            ->state(fn (Contact $record): array => $record->methods
                                ->whereIn('type', [ContactMethodType::Instagram, ContactMethodType::Facebook])
                                ->map(fn (ContactMethod $method): string => $method->type->getLabel().': '.$method->value)
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                        TextEntry::make('source')->badge()->placeholder('—'),
                        TextEntry::make('tags.name')->label('Tags')->badge()->placeholder('—'),
                        TextEntry::make('last_activity_at')->label('Last activity')->since()->placeholder('Never'),
                        TextEntry::make('created_at')->label('Added')->date(),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Companies')
                    ->schema([
                        RepeatableEntry::make('companyLinks')
                            ->hiddenLabel()
                            ->placeholder('Not linked to a company')
                            ->schema([
                                TextEntry::make('company.name')
                                    ->hiddenLabel()
                                    ->weight('semibold')
                                    ->url(fn (CompanyContact $record): string => CompanyResource::getUrl('view', ['record' => $record->company_id])),
                                TextEntry::make('job_title')->hiddenLabel()->color('gray')->placeholder('—'),
                            ])
                            ->columns(2),
                    ]),
            ])
            ->columns(['default' => 1, 'lg' => 2]);
    }

    /**
     * @return list<string>
     */
    protected static function values(Contact $record, ContactMethodType $type): array
    {
        return $record->methods->where('type', $type)->pluck('value')->values()->all();
    }
}

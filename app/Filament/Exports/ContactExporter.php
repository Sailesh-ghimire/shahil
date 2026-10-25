<?php

namespace App\Filament\Exports;

use App\Enums\ContactMethodType;
use App\Models\Contact;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ContactExporter extends Exporter
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('first_name')->label('First name'),
            ExportColumn::make('last_name')->label('Last name'),
            ExportColumn::make('email')
                ->state(fn (Contact $record): string => static::methods($record, ContactMethodType::Email)),
            ExportColumn::make('phone')
                ->state(fn (Contact $record): string => static::methods($record, ContactMethodType::Phone)),
            ExportColumn::make('whatsapp')
                ->label('WhatsApp')
                ->state(fn (Contact $record): string => static::methods($record, ContactMethodType::WhatsApp)),
            ExportColumn::make('companies.name')->label('Companies'),
            ExportColumn::make('source')
                ->formatStateUsing(fn ($state): ?string => $state?->getLabel()),
            ExportColumn::make('owner.email')->label('Owner email'),
            ExportColumn::make('tags.name')->label('Tags'),
            ExportColumn::make('notes')->enabledByDefault(false),
            ExportColumn::make('last_activity_at')->label('Last activity'),
            ExportColumn::make('created_at')->label('Created'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['methods', 'companies', 'owner', 'tags']);
    }

    protected static function methods(Contact $record, ContactMethodType $type): string
    {
        return $record->methods->where('type', $type)->pluck('value')->implode(', ');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your contacts export is ready: '.Number::format($export->successful_rows).' rows.';
    }
}

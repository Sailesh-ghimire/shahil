<?php

namespace App\Filament\Exports;

use App\Models\Lead;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('company_name')->label('Company'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('handle')->label('Social handle'),
            ExportColumn::make('source')->formatStateUsing(fn ($state): ?string => $state?->getLabel()),
            ExportColumn::make('status')->formatStateUsing(fn ($state): ?string => $state?->getLabel()),
            ExportColumn::make('estimated_value')
                ->label('Estimated value')
                ->formatStateUsing(fn (int $state): float => Money::fromMinor($state)),
            ExportColumn::make('owner.email')->label('Owner email'),
            ExportColumn::make('unqualified_reason')->label('Why unqualified'),
            ExportColumn::make('converted_at')->label('Converted'),
            ExportColumn::make('created_at')->label('Received'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your leads export is ready: '.Number::format($export->successful_rows).' rows.';
    }
}

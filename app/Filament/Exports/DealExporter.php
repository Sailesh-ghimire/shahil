<?php

namespace App\Filament\Exports;

use App\Models\Deal;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DealExporter extends Exporter
{
    protected static ?string $model = Deal::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('title'),
            ExportColumn::make('company.name')->label('Company'),
            ExportColumn::make('contact.first_name')->label('Contact first name'),
            ExportColumn::make('contact.last_name')->label('Contact last name'),
            ExportColumn::make('pipeline.name')->label('Pipeline'),
            ExportColumn::make('stage.name')->label('Stage'),
            ExportColumn::make('amount')
                ->label('Value')
                ->formatStateUsing(fn (int $state): float => Money::fromMinor($state)),
            ExportColumn::make('currency'),
            ExportColumn::make('expected_close_on')->label('Expected close'),
            ExportColumn::make('closed_at')->label('Closed'),
            ExportColumn::make('lost_reason')->label('Why lost'),
            ExportColumn::make('owner.email')->label('Owner email'),
            ExportColumn::make('created_at')->label('Created'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['company', 'contact', 'pipeline', 'stage', 'owner']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your deals export is ready: '.Number::format($export->successful_rows).' rows.';
    }
}

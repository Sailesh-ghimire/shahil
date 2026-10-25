<?php

namespace App\Filament\Exports;

use App\Models\Company;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class CompanyExporter extends Exporter
{
    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('industry'),
            ExportColumn::make('website'),
            ExportColumn::make('phone'),
            ExportColumn::make('address'),
            ExportColumn::make('pan_number')->label('PAN / tax number'),
            ExportColumn::make('size')
                ->formatStateUsing(fn ($state): ?string => $state?->value),
            ExportColumn::make('owner.email')->label('Owner email'),
            ExportColumn::make('contacts_count')->label('People')->counts('contacts'),
            ExportColumn::make('tags.name')->label('Tags'),
            ExportColumn::make('notes')->enabledByDefault(false),
            ExportColumn::make('created_at')->label('Created'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['owner', 'tags']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your companies export is ready: '.Number::format($export->successful_rows).' rows.';
    }
}

<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Exports\CompanyExporter;
use App\Filament\Imports\CompanyImporter;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                SpreadsheetImportAction::make()
                    ->importer(CompanyImporter::class)
                    ->authorize('import', Company::class),
                ExportAction::make()
                    ->exporter(CompanyExporter::class)
                    ->authorize('export', Company::class),
            ])->button()->color('gray')->label('Import / export'),
            CreateAction::make()->label('New company'),
        ];
    }
}

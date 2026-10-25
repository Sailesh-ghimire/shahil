<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Exports\LeadExporter;
use App\Filament\Imports\LeadImporter;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('board')
                ->label('Board')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(LeadResource::getUrl('board')),
            ActionGroup::make([
                SpreadsheetImportAction::make()
                    ->importer(LeadImporter::class)
                    ->authorize('import', Lead::class),
                ExportAction::make()
                    ->exporter(LeadExporter::class)
                    ->authorize('export', Lead::class),
            ])->button()->color('gray')->label('Import / export'),
            CreateAction::make()->label('New lead'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Exports\ContactExporter;
use App\Filament\Imports\ContactImporter;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                SpreadsheetImportAction::make()
                    ->importer(ContactImporter::class)
                    ->authorize('import', Contact::class),
                ExportAction::make()
                    ->exporter(ContactExporter::class)
                    ->authorize('export', Contact::class),
            ])->button()->color('gray')->label('Import / export'),
            CreateAction::make()->label('New contact'),
        ];
    }
}

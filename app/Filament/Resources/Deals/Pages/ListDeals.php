<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Exports\DealExporter;
use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('board')
                ->label('Board')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(DealResource::getUrl('board')),
            ExportAction::make()
                ->exporter(DealExporter::class)
                ->authorize('export', Deal::class)
                ->color('gray'),
            CreateAction::make()->label('New deal'),
        ];
    }
}

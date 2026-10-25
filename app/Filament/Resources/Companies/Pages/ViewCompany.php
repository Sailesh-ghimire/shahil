<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Actions\AddFollowUpAction;
use App\Filament\Actions\LogActivityAction;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LogActivityAction::make(),
            AddFollowUpAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Overview';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}

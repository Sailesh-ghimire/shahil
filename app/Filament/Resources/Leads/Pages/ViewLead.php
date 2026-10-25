<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Actions\AddFollowUpAction;
use App\Filament\Actions\LogActivityAction;
use App\Filament\Resources\Leads\Actions\ConvertLeadAction;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConvertLeadAction::make(),
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

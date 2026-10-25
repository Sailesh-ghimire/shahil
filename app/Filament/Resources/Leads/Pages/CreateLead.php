<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Actions\Sales\AssignLeadOwner;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function afterCreate(): void
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        app(AssignLeadOwner::class)->handle($lead);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lead created';
    }
}

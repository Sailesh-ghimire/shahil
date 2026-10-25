<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Actions\AddFollowUpAction;
use App\Filament\Actions\LogActivityAction;
use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    public function getTitle(): string
    {
        /** @var Contact $record */
        $record = $this->getRecord();

        return $record->name;
    }

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

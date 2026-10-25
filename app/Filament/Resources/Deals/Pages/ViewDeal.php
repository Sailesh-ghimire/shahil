<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Actions\AddFollowUpAction;
use App\Filament\Actions\LogActivityAction;
use App\Filament\Resources\Deals\Actions\CloseDealActions;
use App\Filament\Resources\Deals\DealResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewDeal extends ViewRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CloseDealActions::markWon(),
            CloseDealActions::markLost(),
            LogActivityAction::make(),
            AddFollowUpAction::make(),
            EditAction::make(),
            ActionGroup::make([
                CloseDealActions::reopen(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]),
        ];
    }

    /**
     * Line items change the deal value; refresh the overview when they do.
     */
    #[On('deal-lines-changed')]
    public function refreshDeal(): void
    {
        $this->getRecord()->refresh();
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

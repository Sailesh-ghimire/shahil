<?php

namespace App\Filament\Resources\Invitations\Pages;

use App\Filament\Resources\Invitations\InvitationResource;
use App\Filament\Resources\Members\Actions\InviteMemberAction;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected static ?string $title = 'Pending invitations';

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MemberResource::getUrl() => 'Members',
            'Pending invitations',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            InviteMemberAction::make(),
        ];
    }
}

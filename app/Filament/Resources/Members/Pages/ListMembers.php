<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Invitations\InvitationResource;
use App\Filament\Resources\Members\Actions\InviteMemberAction;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invitations')
                ->label('Pending invitations')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->url(InvitationResource::getUrl()),
            InviteMemberAction::make(),
        ];
    }
}

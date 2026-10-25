<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected ?string $subheading = 'Roles apply to this workspace only. Each role is a set of permissions.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New role'),
        ];
    }
}

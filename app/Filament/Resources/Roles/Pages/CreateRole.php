<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Workspaces\SaveRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveRole::class)->handle(currentTeam(), [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => RoleForm::collectPermissions($data),
        ]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Role created';
    }
}

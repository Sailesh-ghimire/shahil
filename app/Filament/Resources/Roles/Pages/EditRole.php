<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Workspaces\SaveRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Role $record, DeleteAction $action): bool {
                    try {
                        app(SaveRole::class)->delete($record);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Role not deleted')
                            ->body($exception->getMessage())
                            ->warning()
                            ->send();

                        $action->halt();
                    }

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();

        return [...$data, ...RoleForm::fillPermissions($role)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        return app(SaveRole::class)->handle(currentTeam(), [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => RoleForm::collectPermissions($data),
        ], $record);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Role saved';
    }
}

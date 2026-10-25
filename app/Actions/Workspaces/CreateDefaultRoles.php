<?php

namespace App\Actions\Workspaces;

use App\Models\Role;
use App\Models\Team;
use App\Support\Permissions\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives a new workspace the five ready-made roles with their default permissions.
 */
class CreateDefaultRoles
{
    public function handle(Team $team): void
    {
        $this->ensurePermissionsExist();

        foreach (PermissionCatalog::defaultRoles() as $name => $definition) {
            $role = Role::forTeam($team)->firstOrCreate(
                ['team_id' => $team->getKey(), 'name' => $name, 'guard_name' => 'web'],
                ['is_system' => true, 'description' => $definition['description']],
            );

            $role->syncPermissions($definition['permissions']);
        }
    }

    public function ensurePermissionsExist(): void
    {
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $missing = array_diff(PermissionCatalog::all(), $existing);

        if ($missing === []) {
            return;
        }

        foreach ($missing as $name) {
            Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

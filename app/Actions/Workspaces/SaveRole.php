<?php

namespace App\Actions\Workspaces;

use App\Models\Role;
use App\Models\Team;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates or updates a workspace role with its permission checklist.
 */
class SaveRole
{
    /**
     * @param  array{name: string, description?: ?string, permissions: list<string>}  $data
     */
    public function handle(Team $team, array $data, ?Role $role = null): Role
    {
        if ($role?->isOwner()) {
            throw new InvalidArgumentException('The Owner role can’t be edited.');
        }

        $permissions = array_values(array_intersect($data['permissions'], PermissionCatalog::all()));

        return DB::transaction(function () use ($team, $data, $role, $permissions): Role {
            $role ??= new Role(['guard_name' => 'web']);
            $role->team_id = $team->getKey();
            $role->name = $data['name'];
            $role->description = $data['description'] ?? null;
            $role->save();

            $role->syncPermissions($permissions);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->isOwner()) {
            throw new InvalidArgumentException('The Owner role can’t be deleted.');
        }

        $inUse = $role->users()->wherePivot(config('permission.column_names.team_foreign_key'), $role->team_id)->count();

        if ($inUse > 0) {
            throw new InvalidArgumentException("This role is held by {$inUse} ".str('member')->plural($inUse).'. Give them another role first.');
        }

        $role->delete();
    }
}

<?php

namespace App\Actions\Workspaces;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;

/**
 * Sets a member's single role inside one workspace.
 */
class AssignWorkspaceRole
{
    public function __construct(protected CurrentWorkspace $workspace) {}

    public function handle(User $user, Team $team, Role|string $role): void
    {
        $this->workspace->run($team, function (Team $team) use ($user, $role): void {
            $role = $role instanceof Role
                ? $role
                : Role::forTeam($team)->where('name', $role)->firstOrFail();

            $user->forgetCachedPermissions();
            $user->syncRoles([$role]);
            $user->forgetCachedPermissions();
        });
    }
}

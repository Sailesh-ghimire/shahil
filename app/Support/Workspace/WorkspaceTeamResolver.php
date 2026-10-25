<?php

namespace App\Support\Workspace;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Makes Spatie's "current team" the same thing as the current workspace,
 * so permission checks and data scoping can never disagree.
 */
class WorkspaceTeamResolver implements PermissionsTeamResolver
{
    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $team = match (true) {
            $id instanceof Team => $id,
            $id === null => null,
            default => Team::find($id),
        };

        app(CurrentWorkspace::class)->set($team);
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return app(CurrentWorkspace::class)->id();
    }
}

<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use InvalidArgumentException;

class ChangeMemberRole
{
    public function __construct(
        protected AssignWorkspaceRole $assignWorkspaceRole,
        protected OwnerGuard $ownerGuard,
    ) {}

    public function handle(Membership $membership, Role $role, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        if ($role->team_id !== $membership->team_id) {
            throw new InvalidArgumentException('That role belongs to another workspace.');
        }

        $this->ownerGuard->ensureActorMayTouchOwners($actor, $membership->team_id, $role, $membership);

        if (! $role->isOwner()) {
            $this->ownerGuard->ensureNotLastOwner($membership);
        }

        $this->assignWorkspaceRole->handle($membership->user, $membership->team, $role);
    }
}

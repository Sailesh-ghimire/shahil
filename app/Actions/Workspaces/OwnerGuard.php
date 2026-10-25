<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use InvalidArgumentException;

/**
 * A workspace always keeps at least one Owner.
 */
class OwnerGuard
{
    public function ensureNotLastOwner(Membership $membership): void
    {
        if ($membership->role()?->name !== PermissionCatalog::OWNER) {
            return;
        }

        if ($this->ownerCount($membership->team_id) <= 1) {
            throw new InvalidArgumentException('A workspace must keep at least one Owner. Make someone else Owner first.');
        }
    }

    /**
     * Only an Owner may hand out the Owner role, or change or remove another Owner
     * (otherwise an Admin could promote themselves and take the workspace over).
     */
    public function ensureActorMayTouchOwners(?User $actor, int $teamId, ?Role $grantedRole = null, ?Membership $target = null): void
    {
        $touchesOwner = ($grantedRole?->isOwner() ?? false)
            || ($target !== null && $target->role()?->name === PermissionCatalog::OWNER);

        if ($touchesOwner && ($actor === null || ! $this->isOwner($actor, $teamId))) {
            throw new InvalidArgumentException('Only an Owner can give, change or remove the Owner role.');
        }
    }

    public function isOwner(User $user, int $teamId): bool
    {
        $owner = Role::forTeam($teamId)->where('name', PermissionCatalog::OWNER)->first();

        return $owner !== null && $owner->users()
            ->wherePivot(config('permission.column_names.team_foreign_key'), $teamId)
            ->whereKey($user->getKey())
            ->exists();
    }

    public function ownerCount(int $teamId): int
    {
        $owner = Role::forTeam($teamId)->where('name', PermissionCatalog::OWNER)->first();

        return $owner === null ? 0 : $owner->users()->wherePivot(config('permission.column_names.team_foreign_key'), $teamId)->count();
    }
}

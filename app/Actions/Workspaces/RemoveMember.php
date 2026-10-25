<?php

namespace App\Actions\Workspaces;

use App\Models\Membership;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    public function __construct(
        protected CurrentWorkspace $workspace,
        protected OwnerGuard $ownerGuard,
    ) {}

    public function handle(Membership $membership, ?User $actor = null): void
    {
        $this->ownerGuard->ensureActorMayTouchOwners($actor ?? auth()->user(), $membership->team_id, target: $membership);
        $this->ownerGuard->ensureNotLastOwner($membership);

        DB::transaction(function () use ($membership): void {
            $user = $membership->user;

            $this->workspace->run($membership->team, function () use ($user): void {
                $user->forgetCachedPermissions();
                $user->syncRoles([]);
            });

            $membership->delete();

            if ($user->last_team_id === $membership->team_id) {
                $user->forceFill(['last_team_id' => null])->save();
            }
        });
    }
}

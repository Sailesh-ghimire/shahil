<?php

namespace App\Actions\Workspaces;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Joins the invited user to the workspace with the role chosen on the invitation.
 */
class AcceptInvitation
{
    public function __construct(protected AssignWorkspaceRole $assignWorkspaceRole) {}

    public function handle(TeamInvitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw new InvalidArgumentException('This invitation has expired or was already used.');
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw new InvalidArgumentException('This invitation was sent to a different email address.');
        }

        if (! $user->hasVerifiedEmail()) {
            throw new InvalidArgumentException('Verify your email address first; the invitation is accepted once you do.');
        }

        DB::transaction(function () use ($invitation, $user): void {
            $team = $invitation->team;

            if (! $user->belongsToTeam($team)) {
                $team->members()->attach($user, [
                    'invited_at' => $invitation->created_at,
                    'joined_at' => now(),
                ]);
            }

            $this->assignWorkspaceRole->handle($user, $team, $invitation->role ?? PermissionCatalog::SALES_REP);

            $invitation->forceFill(['accepted_at' => now()])->save();
        });
    }
}

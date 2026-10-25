<?php

namespace App\Actions\Workspaces;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\WorkspaceInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Invites someone by email. Only a hash of the link's token is stored; the token itself is only in the email.
 */
class InviteMember
{
    public function handle(Team $team, User $inviter, string $email, Role $role): TeamInvitation
    {
        $email = Str::lower(trim($email));

        if ($role->team_id !== $team->getKey()) {
            throw new InvalidArgumentException('That role belongs to another workspace.');
        }

        app(OwnerGuard::class)->ensureActorMayTouchOwners($inviter, $team->getKey(), $role);

        $alreadyMember = $team->members()->where('email', $email)->exists();

        if ($alreadyMember) {
            throw new InvalidArgumentException("{$email} is already a member of this workspace.");
        }

        $token = Str::random(64);

        $invitation = TeamInvitation::query()->updateOrCreate(
            ['team_id' => $team->getKey(), 'email' => $email],
            [
                'role_id' => $role->getKey(),
                'token' => TeamInvitation::hashToken($token),
                'invited_by' => $inviter->getKey(),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ],
        );

        Notification::route('mail', $email)->notify(new WorkspaceInvitation($invitation, $token));

        return $invitation;
    }
}

<?php

namespace App\Listeners;

use App\Actions\Workspaces\AcceptInvitation;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * When someone verifies their email, they join every workspace that invited that address.
 */
class AcceptPendingInvitations
{
    public function __construct(protected AcceptInvitation $acceptInvitation) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        TeamInvitation::query()
            ->pending()
            ->where('email', $user->email)
            ->get()
            ->each(fn (TeamInvitation $invitation) => $this->acceptInvitation->handle($invitation, $user));
    }
}

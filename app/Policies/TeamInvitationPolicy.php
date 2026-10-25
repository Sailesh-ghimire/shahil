<?php

namespace App\Policies;

use App\Models\TeamInvitation;
use App\Models\User;

class TeamInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function delete(User $user, TeamInvitation $invitation): bool
    {
        return $user->can('members.manage');
    }
}

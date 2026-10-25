<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function view(User $user, Membership $membership): bool
    {
        return $user->can('members.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('members.manage');
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->can('members.manage');
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $user->can('members.manage');
    }
}

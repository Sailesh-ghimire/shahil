<?php

namespace App\Policies;

use App\Models\ChannelAccount;
use App\Models\User;

class ChannelAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function update(User $user, ChannelAccount $account): bool
    {
        return $user->can('channels.manage');
    }

    public function delete(User $user, ChannelAccount $account): bool
    {
        return $user->can('channels.manage');
    }
}

<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('contacts.update.any');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('contacts.delete');
    }
}

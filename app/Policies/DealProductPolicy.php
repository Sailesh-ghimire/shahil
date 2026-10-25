<?php

namespace App\Policies;

use App\Models\DealProduct;
use App\Models\User;

/**
 * Line items change the deal value, so changing them needs the same right as editing the deal.
 * The Products tab also checks the open deal itself (LineItemsTable).
 */
class DealProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('deals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('deals.update.any') || $user->can('deals.update.own');
    }

    public function update(User $user, DealProduct $line): bool
    {
        return $user->can('update', $line->deal);
    }

    public function delete(User $user, DealProduct $line): bool
    {
        return $user->can('update', $line->deal);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->create($user);
    }
}

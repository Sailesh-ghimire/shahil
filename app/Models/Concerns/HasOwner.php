<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record with an owner (the "own" in permissions like deals.update.own).
 */
trait HasOwner
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->getAttribute('owner_id') === $user->getKey();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, User|int $user): void
    {
        $query->where($query->qualifyColumn('owner_id'), $user instanceof User ? $user->getKey() : $user);
    }
}

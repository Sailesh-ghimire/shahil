<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pipelines.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('pipelines.manage');
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $user->can('pipelines.manage');
    }

    /**
     * A pipeline with deals can't be deleted; move or delete its deals first.
     */
    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $user->can('pipelines.manage') && ! $pipeline->deals()->withTrashed()->exists();
    }
}

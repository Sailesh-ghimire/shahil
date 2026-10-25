<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps policy abilities to `module.action` permissions, with the "own record" rule for updates:
 * `module.update.any`, or `module.update.own` when the user owns (or is assigned) the record.
 */
abstract class ModulePolicy
{
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->module().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->module().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->module().'.create');
    }

    public function update(User $user, Model $record): bool
    {
        if ($user->can($this->module().'.update.any')) {
            return true;
        }

        return $user->can($this->module().'.update.own') && $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    protected function owns(User $user, Model $record): bool
    {
        $attributes = $record->getAttributes();
        $userId = $user->getKey();

        return ($attributes['owner_id'] ?? null) === $userId
            || ($attributes['assignee_id'] ?? null) === $userId;
    }
}

<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * tasks.manage.any: everyone's tasks. tasks.manage.own: tasks assigned to or created by the user.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.manage.own') || $user->can('tasks.manage.any');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->manages($user, $task);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->manages($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->manages($user, $task);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    protected function manages(User $user, Task $task): bool
    {
        if ($user->can('tasks.manage.any')) {
            return true;
        }

        return $user->can('tasks.manage.own')
            && ($task->assignee_id === $user->getKey() || $task->created_by === $user->getKey());
    }
}

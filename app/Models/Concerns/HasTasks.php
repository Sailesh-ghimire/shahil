<?php

namespace App\Models\Concerns;

use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record that can have follow-ups ("Add follow-up").
 */
trait HasTasks
{
    /**
     * @return MorphMany<Task, $this>
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject');
    }
}

<?php

namespace App\Models\Concerns;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A record with a selling timeline (calls, meetings, notes, messages).
 */
trait HasTimeline
{
    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('happened_at');
    }

    public function logActivity(ActivityType $type, ?string $body = null, ?User $user = null, ?Carbon $happenedAt = null, ?string $title = null, ?string $outcome = null): Activity
    {
        $happenedAt ??= now();

        /** @var Activity $activity */
        $activity = $this->activities()->create([
            'team_id' => $this->getAttribute('team_id'),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'outcome' => $outcome,
            'happened_at' => $happenedAt,
            'user_id' => $user?->getKey() ?? auth()->id(),
        ]);

        return $activity;
    }
}

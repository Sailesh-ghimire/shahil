<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One entry on a record's selling timeline. Not the audit log (see AuditLog).
 *
 * @property int $id
 * @property int $team_id
 * @property string $subject_type
 * @property int $subject_id
 * @property ActivityType $type
 * @property ?string $title
 * @property ?string $body
 * @property ?string $outcome
 * @property Carbon $happened_at
 * @property ?int $user_id
 * @property-read ?Model $subject
 * @property-read ?User $user
 */
class Activity extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'type',
        'title',
        'body',
        'outcome',
        'happened_at',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'happened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep "last activity" on the record fresh for sorting and stale-record rules.
        static::created(function (Activity $activity): void {
            $subject = $activity->subject;

            if ($subject !== null && $subject->isFillable('last_activity_at')) {
                $current = $subject->getAttribute('last_activity_at');

                if ($current === null || $activity->happened_at->greaterThan($current)) {
                    $subject->forceFill(['last_activity_at' => $activity->happened_at])->saveQuietly();
                }
            }
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

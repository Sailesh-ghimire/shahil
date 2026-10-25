<?php

namespace App\Models;

use App\Enums\TaskType;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A follow-up or to-do, optionally attached to a contact, company, lead or deal.
 *
 * @property int $id
 * @property int $team_id
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property string $title
 * @property TaskType $type
 * @property ?string $notes
 * @property Carbon $due_at
 * @property ?int $remind_minutes_before
 * @property ?Carbon $remind_at
 * @property ?Carbon $reminded_at
 * @property ?Carbon $completed_at
 * @property ?int $assignee_id
 * @property ?int $created_by
 * @property bool $is_automatic
 * @property-read ?Model $subject
 * @property-read ?User $assignee
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToWorkspace, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'subject_type',
        'subject_id',
        'title',
        'type',
        'notes',
        'due_at',
        'remind_minutes_before',
        'assignee_id',
        'created_by',
        'is_automatic',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'call',
        'is_automatic' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
            'remind_minutes_before' => 'integer',
            'is_automatic' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            $task->created_by ??= auth()->id();

            if ($task->isDirty(['due_at', 'remind_minutes_before'])) {
                $task->remind_at = $task->remind_minutes_before === null
                    ? null
                    : $task->due_at->copy()->subMinutes($task->remind_minutes_before);
                $task->reminded_at = null;
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_at->isPast();
    }

    public function complete(): void
    {
        $this->forceFill(['completed_at' => now()])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['completed_at' => null])->save();
    }

    /**
     * Moves the task (and its reminder) later by $minutes from now.
     */
    public function snooze(int $minutes): void
    {
        $this->due_at = now()->addMinutes($minutes);
        $this->remind_minutes_before = $this->remind_minutes_before === null ? null : 0;
        $this->save();
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * Reminders that are due and not sent yet, across all workspaces.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeReminderDue(Builder $query): void
    {
        $query->withoutGlobalScope('workspace')
            ->whereNull('completed_at')
            ->whereNull('reminded_at')
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now());
    }
}

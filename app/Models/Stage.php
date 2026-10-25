<?php

namespace App\Models;

use App\Enums\StageKind;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int $pipeline_id
 * @property string $name
 * @property int $sort
 * @property int $probability
 * @property StageKind $kind
 */
class Stage extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'pipeline_id',
        'name',
        'sort',
        'probability',
        'kind',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort' => 0,
        'probability' => 0,
        'kind' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StageKind::class,
            'probability' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Stage $stage): void {
            $stage->team_id ??= Pipeline::query()->withoutGlobalScopes()->whereKey($stage->pipeline_id)->value('team_id');
        });
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isWon(): bool
    {
        return $this->kind === StageKind::Won;
    }

    public function isLost(): bool
    {
        return $this->kind === StageKind::Lost;
    }

    public function isClosed(): bool
    {
        return $this->kind !== StageKind::Open;
    }
}

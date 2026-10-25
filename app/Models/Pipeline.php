<?php

namespace App\Models;

use App\Enums\StageKind;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $sort
 * @property bool $is_default
 */
class Pipeline extends Model
{
    use AuditsChanges, BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'sort',
        'is_default',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort' => 0,
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Stage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('sort');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function firstOpenStage(): ?Stage
    {
        return $this->stages()->where('kind', StageKind::Open)->first();
    }

    public function wonStage(): ?Stage
    {
        return $this->stages()->where('kind', StageKind::Won)->first();
    }

    public function lostStage(): ?Stage
    {
        return $this->stages()->where('kind', StageKind::Lost)->first();
    }

    public static function defaultForCurrentWorkspace(): ?self
    {
        return static::query()->orderByDesc('is_default')->orderBy('sort')->first();
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current workspace and fills team_id on create.
 *
 * With no workspace set (system code such as webhook routing), queries are not scoped;
 * that code must look records up by an id that already identifies the workspace.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $workspaceId = app(CurrentWorkspace::class)->id();

            if ($workspaceId !== null) {
                $query->where($query->qualifyColumn('team_id'), $workspaceId);
            }
        });

        static::creating(function (self $model): void {
            $model->team_id ??= app(CurrentWorkspace::class)->id();
        });
    }

    /**
     * Filament's ownership relationship for tenancy.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

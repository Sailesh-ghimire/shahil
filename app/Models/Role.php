<?php

namespace App\Models;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role inside one workspace. Permissions are global; roles are per workspace.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property ?string $description
 */
class Role extends SpatieRole
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Roles of one workspace, regardless of which workspace the panel is showing.
     *
     * @return Builder<Role>
     */
    public static function forTeam(Team|int $team): Builder
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->where('roles.team_id', $team instanceof Team ? $team->getKey() : $team);
    }

    public function isOwner(): bool
    {
        return $this->name === PermissionCatalog::OWNER;
    }
}

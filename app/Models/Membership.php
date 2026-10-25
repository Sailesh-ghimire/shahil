<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

/**
 * A user's membership of a workspace. The role itself lives in Spatie's model_has_roles.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property ?Carbon $invited_at
 * @property ?Carbon $joined_at
 * @property ?Carbon $last_active_at
 * @property-read Team $team
 * @property-read User $user
 */
class Membership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'last_active_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The member's role in this membership's workspace, read without relying on the current team id.
     */
    public function role(): ?Role
    {
        $registrar = app(PermissionRegistrar::class);

        return Role::query()
            ->withoutGlobalScopes()
            ->where('roles.team_id', $this->team_id)
            ->whereIn('roles.id', function ($query) use ($registrar): void {
                $query->select($registrar->pivotRole)
                    ->from(config('permission.table_names.model_has_roles'))
                    ->where('model_type', $this->user->getMorphClass())
                    ->where(config('permission.column_names.model_morph_key'), $this->user_id)
                    ->where($registrar->teamsKey, $this->team_id);
            })
            ->first();
    }
}

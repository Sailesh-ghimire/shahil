<?php

namespace App\Actions\Sales;

use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;

/**
 * Round-robin lead assignment across members who can work their own leads.
 * Manual assignment always wins; this only fills an empty owner.
 */
class AssignLeadOwner
{
    public function __construct(protected CurrentWorkspace $workspace) {}

    public function handle(Lead $lead): ?User
    {
        if ($lead->owner_id !== null) {
            return $lead->owner;
        }

        $team = Team::query()->findOrFail($lead->team_id);
        $candidates = $this->candidates($team);

        if ($candidates === []) {
            return null;
        }

        $settings = $team->settings ?? [];
        $lastId = $settings['last_assigned_user_id'] ?? null;
        $index = array_search($lastId, $candidates, true);
        $nextId = $candidates[$index === false ? 0 : ($index + 1) % count($candidates)];

        $team->forceFill(['settings' => [...$settings, 'last_assigned_user_id' => $nextId]])->save();

        $lead->forceFill(['owner_id' => $nextId])->save();

        return User::query()->find($nextId);
    }

    /**
     * Members (ordered by id) who can update their own leads but aren't owners or admins.
     *
     * @return list<int>
     */
    protected function candidates(Team $team): array
    {
        return $this->workspace->run($team, function () use ($team): array {
            return $team->members()
                ->orderBy('users.id')
                ->get()
                ->filter(function (User $user): bool {
                    $user->forgetCachedPermissions();

                    return $user->hasPermissionTo('leads.update.own') && ! $user->hasPermissionTo('leads.update.any');
                })
                ->map(fn (User $user): int => $user->getKey())
                ->values()
                ->all();
        });
    }
}

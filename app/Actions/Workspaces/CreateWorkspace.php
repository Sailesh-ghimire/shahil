<?php

namespace App\Actions\Workspaces;

use App\Events\WorkspaceCreated;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Creates a workspace with its default roles and makes the creator its Owner.
 */
class CreateWorkspace
{
    public function __construct(
        protected CreateDefaultRoles $createDefaultRoles,
        protected AssignWorkspaceRole $assignWorkspaceRole,
    ) {}

    /**
     * @param  array{name: string, currency?: string, timezone?: string}  $data
     */
    public function handle(User $owner, array $data): Team
    {
        return DB::transaction(function () use ($owner, $data): Team {
            $team = Team::query()->create([
                'name' => $data['name'],
                'currency' => $data['currency'] ?? 'NPR',
                'timezone' => $data['timezone'] ?? 'Asia/Kathmandu',
            ]);

            $team->members()->attach($owner, ['joined_at' => now()]);

            $this->createDefaultRoles->handle($team);
            $this->assignWorkspaceRole->handle($owner, $team, PermissionCatalog::OWNER);

            WorkspaceCreated::dispatch($team);

            return $team;
        });
    }
}

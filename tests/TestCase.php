<?php

namespace Tests;

use App\Actions\Workspaces\AssignWorkspaceRole;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A workspace with default roles, owned by $owner (a new user when omitted).
     */
    protected function createWorkspace(?User $owner = null, string $name = 'Acme Traders'): Team
    {
        $owner ??= User::factory()->create();

        $team = app(CreateWorkspace::class)->handle($owner, ['name' => $name]);

        setPermissionsTeamId(null);

        return $team;
    }

    /**
     * A new member of $team holding $role.
     */
    protected function memberOf(Team $team, string $role = PermissionCatalog::SALES_REP): User
    {
        $user = User::factory()->create();

        $team->members()->attach($user, ['joined_at' => now()]);
        app(AssignWorkspaceRole::class)->handle($user, $team, $role);

        return $user;
    }

    /**
     * Sign in and enter the workspace the way the panel does.
     */
    protected function actingInWorkspace(User $user, Team $team): static
    {
        $this->actingAs($user);

        Filament::setCurrentPanel('app');
        Filament::setTenant($team);
        Filament::bootCurrentPanel();
        setPermissionsTeamId($team);
        $user->forgetCachedPermissions();

        return $this;
    }
}

<?php

namespace Database\Seeders;

use App\Actions\Workspaces\AssignWorkspaceRole;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Database\Seeder;

/**
 * The two sample workspaces from the panel preview. Every demo user's password is "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            'aarav' => ['Aarav Karki', 'aarav@acme.example'],
            'meera' => ['Meera Shah', 'meera@acme.example'],
            'sanjay' => ['Sanjay Adhikari', 'sanjay@acme.example'],
            'nisha' => ['Nisha Pandey', 'nisha@acme.example'],
            'pema' => ['Pema Lama', 'pema@himalaya.example'],
            'dipika' => ['Dipika Lama', 'dipika@himalaya.example'],
        ])->map(fn (array $user): User => User::query()->firstOrCreate(
            ['email' => $user[1]],
            ['name' => $user[0], 'password' => 'password', 'email_verified_at' => now()],
        ));

        $acme = $this->workspace($users['aarav'], 'Acme Traders', [
            [$users['meera'], PermissionCatalog::SALES_REP],
            [$users['sanjay'], PermissionCatalog::MANAGER],
            [$users['nisha'], PermissionCatalog::VIEWER],
        ]);

        $himalaya = $this->workspace($users['pema'], 'Himalaya Exports', [
            [$users['aarav'], PermissionCatalog::ADMIN],
            [$users['dipika'], PermissionCatalog::SALES_REP],
        ]);

        app(CurrentWorkspace::class)->run($acme, fn () => $this->call(AcmeDemoDataSeeder::class, parameters: ['team' => $acme, 'users' => $users]));
        app(CurrentWorkspace::class)->run($himalaya, fn () => $this->call(HimalayaDemoDataSeeder::class, parameters: ['team' => $himalaya, 'users' => $users]));
    }

    /**
     * @param  list<array{0: User, 1: string}>  $members
     */
    protected function workspace(User $owner, string $name, array $members): Team
    {
        $team = Team::query()->where('name', $name)->first()
            ?? app(CreateWorkspace::class)->handle($owner, ['name' => $name]);

        foreach ($members as [$user, $role]) {
            if (! $user->belongsToTeam($team)) {
                $team->members()->attach($user, ['joined_at' => now()]);
            }

            app(AssignWorkspaceRole::class)->handle($user, $team, $role);
        }

        return $team;
    }
}

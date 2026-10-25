<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every tenant request (including Livewire updates): sets the current workspace
 * for data scoping and Spatie permission checks, and remembers it as the user's last one.
 */
class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = Filament::getTenant();
        $user = $request->user();

        if ($team instanceof Team) {
            setPermissionsTeamId($team);

            if ($user instanceof User) {
                $user->forgetCachedPermissions();

                if ($user->last_team_id !== $team->getKey()) {
                    $user->forceFill(['last_team_id' => $team->getKey()])->saveQuietly();
                }
            }
        }

        return $next($request);
    }
}

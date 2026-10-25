<?php

use App\Models\Team;
use Filament\Facades\Filament;

if (! function_exists('currentTeam')) {
    /**
     * The workspace the panel is showing. Only call inside tenant-aware panel code.
     */
    function currentTeam(): Team
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            throw new LogicException('No workspace is selected.');
        }

        return $team;
    }
}

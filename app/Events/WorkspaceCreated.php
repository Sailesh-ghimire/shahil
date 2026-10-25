<?php

namespace App\Events;

use App\Models\Team;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lets later modules seed per-workspace defaults (pipelines, settings) without coupling to CreateWorkspace.
 */
class WorkspaceCreated
{
    use Dispatchable;

    public function __construct(public Team $team) {}
}

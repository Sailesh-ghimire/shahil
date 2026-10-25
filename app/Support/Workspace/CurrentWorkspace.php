<?php

namespace App\Support\Workspace;

use App\Models\Team;
use Closure;
use Filament\Facades\Filament;

/**
 * Holds the workspace (team) the current request, job or command is working in.
 *
 * Filament only scopes queries inside panel resources. Everything else (queued jobs,
 * webhooks, imports, the scheduler) reads the workspace from here, through the
 * BelongsToWorkspace global scope and the Spatie team resolver.
 */
class CurrentWorkspace
{
    protected ?Team $team = null;

    public function set(?Team $team): void
    {
        $this->team = $team;
    }

    /**
     * Falls back to the panel's tenant, which Filament also restores inside import/export jobs.
     */
    public function get(): ?Team
    {
        if ($this->team !== null) {
            return $this->team;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    public function id(): ?int
    {
        return $this->get()?->getKey();
    }

    public function check(): bool
    {
        return $this->get() !== null;
    }

    /**
     * Run a callback inside a workspace, restoring the previous one afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(Team): TReturn  $callback
     * @return TReturn
     */
    public function run(Team $team, Closure $callback): mixed
    {
        $previous = $this->team;

        $this->set($team);

        try {
            return $callback($team);
        } finally {
            $this->set($previous);
        }
    }
}

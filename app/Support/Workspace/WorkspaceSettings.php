<?php

namespace App\Support\Workspace;

use App\Models\Team;

/**
 * Typed access to the `teams.settings` JSON column.
 */
class WorkspaceSettings
{
    public const DEFAULT_STALE_DEAL_DAYS = 7;

    public function __construct(protected Team $team) {}

    public static function for(Team $team): self
    {
        return new self($team);
    }

    public function staleDealDays(): int
    {
        return (int) ($this->team->settings['stale_deal_days'] ?? self::DEFAULT_STALE_DEAL_DAYS);
    }

    /**
     * Monthly revenue target in minor units (0 = no target).
     */
    public function monthlyRevenueTarget(): int
    {
        return (int) ($this->team->settings['monthly_revenue_target'] ?? 0);
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Deleted records can be restored for 30 days; `model:prune` (daily) removes them after that.
 */
trait PurgesTrashedRecords
{
    use Prunable;

    public const RESTORE_WINDOW_DAYS = 30;

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->withoutGlobalScope('workspace')
            ->where('deleted_at', '<=', now()->subDays(self::RESTORE_WINDOW_DAYS));
    }
}

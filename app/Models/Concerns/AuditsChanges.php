<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Writes create/update/delete entries (old and new values) to the audit log.
 */
trait AuditsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['last_activity_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('audit');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * An audit entry ("who changed what"). Different from the selling timeline (Activity).
 *
 * @property ?int $team_id
 */
class AuditLog extends SpatieActivity
{
    use BelongsToWorkspace;
}

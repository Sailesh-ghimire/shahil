<?php

namespace App\Support\Audit;

use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns an audit entry's old/new values into "stage: Prospecting → Proposal; amount: 0 → 5000".
 */
class AuditChanges
{
    public static function summarize(Activity $entry): string
    {
        /** @var Collection<string, array<string, mixed>>|null $changes */
        $changes = $entry->attribute_changes;

        if ($changes === null || $changes->isEmpty()) {
            return '';
        }

        $new = $changes->get('attributes', []);
        $old = $changes->get('old', []);

        return collect($new)
            ->map(function (mixed $value, string $attribute) use ($old): string {
                $label = Str::of($attribute)->replace('_id', '')->replace('_', ' ')->toString();

                if (array_key_exists($attribute, $old)) {
                    return "{$label}: ".static::format($old[$attribute]).' → '.static::format($value);
                }

                return "{$label}: ".static::format($value);
            })
            ->implode('; ');
    }

    protected static function format(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => json_encode($value) ?: '',
            default => Str::limit((string) $value, 80),
        };
    }
}

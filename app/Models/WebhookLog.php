<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every webhook payload Meta sends, kept for 14 days for debugging. Not workspace-scoped:
 * the workspace is only known after routing.
 *
 * @property int $id
 * @property string $channel
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property ?Carbon $processed_at
 * @property ?string $error
 * @property Carbon $created_at
 */
class WebhookLog extends Model
{
    use MassPrunable;

    public const KEEP_DAYS = 14;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel',
        'payload',
        'signature_valid',
        'processed_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<WebhookLog>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<=', now()->subDays(self::KEEP_DAYS));
    }
}

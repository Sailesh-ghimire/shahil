<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A connected WhatsApp number, Facebook Page or Instagram account. Webhooks find the workspace
 * from external_id (phone number id, Page id, Instagram account id).
 *
 * @property int $id
 * @property int $team_id
 * @property Channel $channel
 * @property string $external_id
 * @property ?string $business_account_id WhatsApp Business Account id (for templates).
 * @property string $name
 * @property ?string $display_handle
 * @property string $access_token Encrypted at rest.
 * @property ChannelAccountStatus $status
 * @property ?string $status_message
 * @property ?Carbon $token_expires_at
 * @property ?Carbon $last_checked_at
 * @property ?int $connected_by
 */
class ChannelAccount extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'channel',
        'external_id',
        'business_account_id',
        'name',
        'display_handle',
        'access_token',
        'status',
        'status_message',
        'token_expires_at',
        'last_checked_at',
        'connected_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'connected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ChannelAccountStatus::class,
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Webhook routing: find the account in any workspace by the id Meta sends.
     */
    public static function findForWebhook(Channel $channel, string $externalId): ?self
    {
        return static::query()
            ->withoutGlobalScope('workspace')
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            ->where('status', '!=', ChannelAccountStatus::Disconnected)
            ->first();
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return HasMany<WhatsAppTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }

    public function isUsable(): bool
    {
        return $this->status === ChannelAccountStatus::Connected;
    }

    /**
     * @param  Builder<ChannelAccount>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', ChannelAccountStatus::Disconnected);
    }
}

<?php

namespace App\Models;

use App\Enums\Channel;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One chat thread with one person on one connected account.
 *
 * @property int $id
 * @property int $team_id
 * @property int $channel_account_id
 * @property string $external_thread_id WhatsApp wa_id, Messenger PSID or Instagram-scoped id.
 * @property ?string $display_name
 * @property ?string $handle
 * @property ?int $contact_id
 * @property ?int $lead_id
 * @property ?int $assignee_id
 * @property ?Carbon $last_message_at
 * @property ?string $last_message_preview
 * @property ?Carbon $last_inbound_at
 * @property ?Carbon $awaiting_reply_since
 * @property int $unread_count
 * @property-read ChannelAccount $channelAccount
 * @property-read ?Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?User $assignee
 */
class Conversation extends Model
{
    use BelongsToWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'channel_account_id',
        'external_thread_id',
        'display_name',
        'handle',
        'contact_id',
        'lead_id',
        'assignee_id',
        'last_message_at',
        'last_message_preview',
        'last_inbound_at',
        'awaiting_reply_since',
        'unread_count',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unread_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'awaiting_reply_since' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChannelAccount, $this>
     */
    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function channel(): Channel
    {
        return $this->channelAccount->channel;
    }

    /**
     * True while a free-form reply is allowed (24 hours after the customer's last message).
     */
    public function isWithinReplyWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->greaterThan(now()->subHours($this->channel()->replyWindowHours()));
    }

    /**
     * The record this chat belongs to on the timeline: the contact if linked, otherwise the lead.
     */
    public function timelineSubject(): Contact|Lead|null
    {
        return $this->contact ?? $this->lead;
    }

    public function title(): string
    {
        return $this->contact->name ?? $this->lead->name ?? $this->display_name ?? $this->handle ?? $this->external_thread_id;
    }
}

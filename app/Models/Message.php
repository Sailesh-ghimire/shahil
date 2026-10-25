<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $conversation_id
 * @property string $direction "in" or "out".
 * @property ?string $external_id Meta's message id; unique, so webhook retries never duplicate.
 * @property string $type
 * @property ?string $body
 * @property ?string $media_external_id
 * @property ?string $media_url
 * @property ?string $media_path
 * @property ?string $media_mime
 * @property ?string $template_name
 * @property MessageStatus $status
 * @property ?string $error
 * @property ?int $sent_by
 * @property ?int $response_seconds Seconds from the oldest unanswered inbound message to this reply.
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $read_at
 * @property Carbon $created_at
 * @property-read Conversation $conversation
 * @property-read ?User $sender
 */
class Message extends Model
{
    use BelongsToWorkspace;

    public const INBOUND = 'in';

    public const OUTBOUND = 'out';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'conversation_id',
        'direction',
        'external_id',
        'type',
        'body',
        'media_external_id',
        'media_url',
        'media_path',
        'media_mime',
        'template_name',
        'status',
        'error',
        'sent_by',
        'response_seconds',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'response_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === self::INBOUND;
    }

    /**
     * Applies a delivery status from a status webhook, never moving backwards.
     */
    public function applyStatus(MessageStatus $status, ?Carbon $at = null, ?string $error = null): void
    {
        if ($status->rank() <= $this->status->rank() && $status !== MessageStatus::Failed) {
            return;
        }

        $at ??= now();

        $this->status = $status;
        $this->error = $status === MessageStatus::Failed ? ($error ?? 'Not delivered') : $this->error;
        $this->sent_at ??= $at;

        if ($status === MessageStatus::Delivered || $status === MessageStatus::Read) {
            $this->delivered_at ??= $at;
        }

        if ($status === MessageStatus::Read) {
            $this->read_at ??= $at;
        }

        $this->save();
    }

    public function preview(): string
    {
        return match (true) {
            filled($this->body) => str((string) $this->body)->squish()->limit(80)->toString(),
            $this->template_name !== null => 'Template: '.$this->template_name,
            default => '['.ucfirst($this->type).']',
        };
    }
}

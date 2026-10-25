<?php

namespace App\Integrations\Meta\Data;

use Illuminate\Support\Carbon;

/**
 * A customer's message, normalised from any channel's webhook.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $accountExternalId,
        public string $threadId,
        public string $externalMessageId,
        public string $type,
        public ?string $body,
        public Carbon $sentAt,
        public ?string $senderName = null,
        public ?string $senderPhone = null,
        public ?string $mediaId = null,
        public ?string $mediaUrl = null,
        public ?string $mediaMime = null,
    ) {}

    public function hasMedia(): bool
    {
        return $this->mediaId !== null || $this->mediaUrl !== null;
    }
}

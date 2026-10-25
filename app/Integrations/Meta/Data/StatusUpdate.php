<?php

namespace App\Integrations\Meta\Data;

use App\Enums\MessageStatus;
use Illuminate\Support\Carbon;

/**
 * Sent / delivered / read / failed for one of our outgoing messages.
 */
final readonly class StatusUpdate
{
    public function __construct(
        public string $accountExternalId,
        public string $externalMessageId,
        public MessageStatus $status,
        public Carbon $at,
        public ?string $error = null,
    ) {}
}

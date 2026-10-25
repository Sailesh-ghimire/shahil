<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageStatus: string implements HasLabel
{
    case Received = 'received';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Status webhooks can arrive out of order; never move a message backwards.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Received, self::Queued => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Failed => 4,
        };
    }
}

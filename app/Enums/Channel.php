<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Channel: string implements HasColor, HasLabel
{
    case WhatsApp = 'whatsapp';
    case Facebook = 'facebook';
    case Instagram = 'instagram';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::WhatsApp => 'success',
            self::Facebook => 'info',
            self::Instagram => 'danger',
        };
    }

    public function recordSource(): RecordSource
    {
        return match ($this) {
            self::WhatsApp => RecordSource::WhatsApp,
            self::Facebook => RecordSource::Facebook,
            self::Instagram => RecordSource::Instagram,
        };
    }

    public function activityType(): ActivityType
    {
        return match ($this) {
            self::WhatsApp => ActivityType::WhatsApp,
            self::Facebook => ActivityType::Facebook,
            self::Instagram => ActivityType::Instagram,
        };
    }

    public function contactMethodType(): ContactMethodType
    {
        return match ($this) {
            self::WhatsApp => ContactMethodType::WhatsApp,
            self::Facebook => ContactMethodType::Facebook,
            self::Instagram => ContactMethodType::Instagram,
        };
    }

    /**
     * Hours after the customer's last message during which a free-form reply is allowed.
     */
    public function replyWindowHours(): int
    {
        return 24;
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactMethodType: string implements HasLabel
{
    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Facebook = 'facebook';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
        };
    }

    public function isPhoneNumber(): bool
    {
        return $this === self::Phone || $this === self::WhatsApp;
    }
}

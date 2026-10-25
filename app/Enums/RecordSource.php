<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a lead or contact came from.
 */
enum RecordSource: string implements HasColor, HasLabel
{
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Website = 'website';
    case Referral = 'referral';
    case Import = 'import';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::Import => 'Import',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::WhatsApp => 'success',
            self::Instagram => 'danger',
            self::Facebook => 'info',
            self::Website, self::Referral => 'primary',
            self::Import, self::Manual => 'gray',
        };
    }
}

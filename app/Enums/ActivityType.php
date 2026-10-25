<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ActivityType: string implements HasColor, HasIcon, HasLabel
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Note = 'note';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case StageChange = 'stage_change';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Note => 'Note',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::StageChange => 'Stage change',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Call => 'primary',
            self::Meeting => 'info',
            self::Note => 'gray',
            self::Email => 'info',
            self::WhatsApp => 'success',
            self::Instagram => 'danger',
            self::Facebook => 'info',
            self::StageChange => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Call => Heroicon::OutlinedPhone,
            self::Meeting => Heroicon::OutlinedUsers,
            self::Note => Heroicon::OutlinedPencilSquare,
            self::Email => Heroicon::OutlinedEnvelope,
            self::WhatsApp, self::Instagram, self::Facebook => Heroicon::OutlinedChatBubbleLeftRight,
            self::StageChange => Heroicon::OutlinedArrowsRightLeft,
        };
    }

    /**
     * Types a person can log by hand (messages and stage changes are logged by the system).
     *
     * @return list<self>
     */
    public static function loggable(): array
    {
        return [self::Call, self::Meeting, self::Note, self::Email];
    }
}

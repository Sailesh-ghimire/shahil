<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskType: string implements HasIcon, HasLabel
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Message = 'message';
    case ToDo = 'todo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::Message => 'Message',
            self::ToDo => 'To-do',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Call => Heroicon::OutlinedPhone,
            self::Email => Heroicon::OutlinedEnvelope,
            self::Meeting => Heroicon::OutlinedUsers,
            self::Message => Heroicon::OutlinedChatBubbleLeft,
            self::ToDo => Heroicon::OutlinedCheckCircle,
        };
    }
}

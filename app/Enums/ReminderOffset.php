<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Minutes before the due time that the reminder fires.
 */
enum ReminderOffset: int implements HasLabel
{
    case AtDueTime = 0;
    case FifteenMinutes = 15;
    case OneHour = 60;
    case OneDay = 1440;

    public function getLabel(): string
    {
        return match ($this) {
            self::AtDueTime => 'At due time',
            self::FifteenMinutes => '15 minutes before',
            self::OneHour => '1 hour before',
            self::OneDay => '1 day before',
        };
    }
}

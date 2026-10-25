<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChannelAccountStatus: string implements HasColor, HasLabel
{
    case Connected = 'connected';
    case ReconnectNeeded = 'reconnect_needed';
    case Disconnected = 'disconnected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::ReconnectNeeded => 'Reconnect needed',
            self::Disconnected => 'Disconnected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::ReconnectNeeded => 'warning',
            self::Disconnected => 'gray',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
    case Converted = 'converted';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Unqualified => 'Unqualified',
            self::Converted => 'Converted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'primary',
            self::Qualified => 'success',
            self::Unqualified => 'gray',
            self::Converted => 'success',
        };
    }

    /**
     * The columns on the Leads board. Converted leads leave the board and stay read-only.
     *
     * @return list<self>
     */
    public static function boardColumns(): array
    {
        return [self::New, self::Contacted, self::Qualified, self::Unqualified];
    }
}

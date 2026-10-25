<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompanySize: string implements HasLabel
{
    case Micro = '1-10';
    case Small = '11-50';
    case Medium = '51-200';
    case Large = '201-1000';
    case Enterprise = '1000+';

    public function getLabel(): string
    {
        return $this->value.' people';
    }
}

<?php

namespace App\Filament\Forms;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * Shows rupees (or the workspace currency) and stores integer minor units.
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->prefix(fn (): string => in_array(static::currency(), ['NPR', 'INR'], true) ? 'Rs' : static::currency())
            ->formatStateUsing(fn (?int $state): ?float => $state === null ? null : Money::fromMinor($state))
            ->dehydrateStateUsing(fn (mixed $state): int => Money::toMinor($state));
    }

    public static function currency(): string
    {
        return currentTeam()->currency;
    }
}

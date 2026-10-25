<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Select;

/**
 * Picks a member of the current workspace as the record's owner. Defaults to the signed-in user.
 */
class OwnerSelect
{
    public static function make(string $name = 'owner_id', string $label = 'Owner'): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (): array => static::options())
            ->default(fn (): ?int => auth()->id())
            ->searchable()
            ->preload();
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return currentTeam()->members()->orderBy('name')->pluck('users.name', 'users.id')->all();
    }
}

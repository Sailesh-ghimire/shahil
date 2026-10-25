<?php

namespace App\Filament\Forms;

use App\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TagsSelect
{
    public static function make(): Select
    {
        return Select::make('tags')
            ->relationship('tags', 'name')
            ->multiple()
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')
                    ->required()
                    ->maxLength(60),
            ])
            ->createOptionUsing(fn (array $data): int => Tag::query()->firstOrCreate([
                'team_id' => currentTeam()->getKey(),
                'name' => trim($data['name']),
            ])->getKey());
    }
}

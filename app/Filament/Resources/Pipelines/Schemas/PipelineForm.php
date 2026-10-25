<?php

namespace App\Filament\Resources\Pipelines\Schemas;

use App\Enums\StageKind;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PipelineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pipeline')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(80),
                        Toggle::make('is_default')
                            ->label('Default pipeline')
                            ->helperText('New deals and converted leads go here.')
                            ->inline(false),
                    ]),
                Section::make('Stages')
                    ->description('Drag to reorder. Deals in a Won or Lost stage count as closed.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('stages')
                            ->hiddenLabel()
                            ->relationship('stages')
                            ->orderColumn('sort')
                            ->reorderable()
                            ->minItems(3)
                            ->table([
                                Repeater\TableColumn::make('Stage'),
                                Repeater\TableColumn::make('Win probability')->width('180px'),
                                Repeater\TableColumn::make('Kind')->width('160px'),
                            ])
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(60),
                                TextInput::make('probability')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(10)
                                    ->required(),
                                Select::make('kind')
                                    ->options(StageKind::class)
                                    ->default(StageKind::Open->value)
                                    ->required(),
                            ])
                            ->rule(static::stageKindsRule())
                            ->addActionLabel('Add stage'),
                    ]),
            ]);
    }

    /**
     * At least one open stage, exactly one Won and one Lost.
     */
    public static function stageKindsRule(): Closure
    {
        return function (): Closure {
            return function (string $attribute, mixed $value, Closure $fail): void {
                /** @var array<string, array<string, mixed>> $stages */
                $stages = is_array($value) ? $value : [];

                $kinds = collect($stages)->map(function (array $stage): ?string {
                    $kind = $stage['kind'] ?? null;

                    return $kind instanceof StageKind ? $kind->value : $kind;
                });

                if ($kinds->filter(fn (?string $kind): bool => $kind === StageKind::Open->value)->isEmpty()) {
                    $fail('Add at least one open stage.');
                }

                foreach ([StageKind::Won, StageKind::Lost] as $kind) {
                    if ($kinds->filter(fn (?string $value): bool => $value === $kind->value)->count() !== 1) {
                        $fail("A pipeline needs exactly one {$kind->getLabel()} stage.");
                    }
                }
            };
        };
    }
}

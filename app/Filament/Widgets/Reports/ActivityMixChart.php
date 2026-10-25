<?php

namespace App\Filament\Widgets\Reports;

use App\Enums\ActivityType;
use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

class ActivityMixChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Activity mix';

    protected ?string $description = 'What does the team spend time on?';

    protected static ?int $sort = 7;

    protected function getData(): array
    {
        $mix = $this->reportFilters()->remember('activity-mix', fn (): array => $this->metrics()->activityMix());
        $colors = self::colors(count($mix));

        return [
            'datasets' => [[
                'label' => 'Activities',
                'data' => array_values($mix),
                'backgroundColor' => array_map(fn (string $color): string => self::transparent($color, 0.6), $colors),
                'borderColor' => $colors,
            ]],
            'labels' => array_map(fn (string $type): string => ActivityType::tryFrom($type)?->getLabel() ?? $type, array_keys($mix)),
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}

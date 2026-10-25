<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use App\Support\Money;
use Filament\Widgets\ChartWidget;

class PipelineByStageChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Pipeline value by stage';

    protected ?string $description = 'Where is the money sitting?';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $stages = $this->reportFilters()->remember('pipeline-stages', fn () => $this->metrics()->pipelineByStage()->all());

        return [
            'datasets' => [[
                'label' => 'Open value (Rs)',
                'data' => array_map(fn (object $stage): float => Money::fromMinor($stage->value), $stages),
                'backgroundColor' => self::colors(count($stages)),
                'borderRadius' => 6,
            ]],
            'labels' => array_map(fn (object $stage): string => "{$stage->name} ({$stage->deals})", $stages),
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

class WonLostByRepChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Won vs lost by rep';

    protected ?string $description = 'Who closes, who loses?';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $reps = $this->reportFilters()->remember('won-lost-by-rep', fn () => $this->metrics()->wonLostByRep()->all());

        return [
            'datasets' => [
                ['label' => 'Won', 'data' => array_map(fn (object $rep): int => $rep->won, $reps), 'backgroundColor' => '#10b981', 'borderRadius' => 4],
                ['label' => 'Lost', 'data' => array_map(fn (object $rep): int => $rep->lost, $reps), 'backgroundColor' => '#ef4444', 'borderRadius' => 4],
            ],
            'labels' => array_map(fn (object $rep): string => $rep->name, $reps),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

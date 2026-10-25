<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

class LostReasonsChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Lost reasons';

    protected ?string $description = 'Why do we lose deals?';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $reasons = $this->reportFilters()->remember('lost-reasons', fn (): array => $this->metrics()->lostReasons());

        return [
            'datasets' => [[
                'label' => 'Deals',
                'data' => array_values($reasons),
                'backgroundColor' => array_reverse(self::colors(count($reasons))),
            ]],
            'labels' => array_keys($reasons),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

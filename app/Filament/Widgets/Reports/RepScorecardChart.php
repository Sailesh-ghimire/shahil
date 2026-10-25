<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

/**
 * Each rep against the best rep on five measures (100 = best in the team; win rate is the actual %).
 */
class RepScorecardChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Rep scorecard';

    protected ?string $description = 'Where does each rep need coaching? 100 = best in the team.';

    protected static ?int $sort = 8;

    protected function getData(): array
    {
        $reps = $this->reportFilters()->remember('rep-scorecard', fn () => $this->metrics()->repScorecard()->all());
        $colors = self::colors(count($reps));

        return [
            'datasets' => array_map(fn (object $rep, int $i): array => [
                'label' => $rep->name,
                'data' => $rep->scores,
                'borderColor' => $colors[$i],
                'backgroundColor' => self::transparent($colors[$i], 0.15),
                'pointBackgroundColor' => $colors[$i],
            ], $reps, array_keys($reps)),
            'labels' => ['Activities', 'Deals won', 'Revenue', 'Win rate', 'Leads converted'],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['display' => false]]]];
    }

    protected function getType(): string
    {
        return 'radar';
    }
}

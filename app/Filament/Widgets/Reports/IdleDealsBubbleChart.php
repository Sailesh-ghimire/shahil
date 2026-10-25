<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use App\Support\Money;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Open deals: x = days idle, y = value, bubble size = win probability.
 */
class IdleDealsBubbleChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Open deals: value vs days idle';

    protected ?string $description = 'Which big deals are going quiet? Bigger bubble = higher win probability.';

    protected static ?int $sort = 9;

    protected function getData(): array
    {
        $deals = $this->reportFilters()->remember('idle-deals', fn () => $this->metrics()->idleOpenDeals()->all());

        return [
            'datasets' => [[
                'label' => 'Open deals',
                'data' => array_map(fn (object $deal): array => [
                    'x' => $deal->idle,
                    'y' => Money::fromMinor($deal->value),
                    'r' => max(4, (int) round($deal->probability / 5)),
                    'title' => $deal->title,
                ], $deals),
                'backgroundColor' => array_map(fn (object $deal): string => $deal->idle >= 7 ? 'rgba(239, 68, 68, 0.5)' : 'rgba(245, 158, 11, 0.5)', $deals),
            ]],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.raw.title}: Rs ${ctx.raw.y.toLocaleString('en-IN')}, ${ctx.raw.x} days idle`,
                        },
                    },
                },
                scales: {
                    x: { title: { display: true, text: 'Days since last activity' }, min: 0 },
                    y: { title: { display: true, text: 'Value (Rs)' }, min: 0 },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bubble';
    }
}

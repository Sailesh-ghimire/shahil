<?php

namespace App\Filament\Widgets\Reports;

use App\Enums\Channel;
use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

class FirstReplyTimeChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'First reply time by channel';

    protected ?string $description = 'Do we answer messages fast enough? Average minutes to the first reply.';

    protected static ?int $sort = 10;

    protected function getData(): array
    {
        $minutes = $this->reportFilters()->remember('first-reply', fn (): array => $this->metrics()->firstReplyMinutesByChannel());
        $channels = Channel::cases();

        return [
            'datasets' => [[
                'label' => 'Minutes',
                'data' => array_map(fn (Channel $channel): float => $minutes[$channel->value] ?? 0, $channels),
                'backgroundColor' => ['#15803d', '#1d4ed8', '#be185d'],
                'borderRadius' => 6,
            ]],
            'labels' => array_map(fn (Channel $channel): string => $channel->getLabel(), $channels),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['title' => ['display' => true, 'text' => 'Minutes']]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

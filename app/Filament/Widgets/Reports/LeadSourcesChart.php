<?php

namespace App\Filament\Widgets\Reports;

use App\Enums\RecordSource;
use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\ChartWidget;

class LeadSourcesChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Lead sources';

    protected ?string $description = 'Which channel brings leads?';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $sources = $this->reportFilters()->remember('lead-sources', fn (): array => $this->metrics()->leadSources());

        return [
            'datasets' => [[
                'label' => 'Leads',
                'data' => array_values($sources),
                'backgroundColor' => self::colors(count($sources)),
            ]],
            'labels' => array_map(fn (string $source): string => RecordSource::tryFrom($source)?->getLabel() ?? $source, array_keys($sources)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

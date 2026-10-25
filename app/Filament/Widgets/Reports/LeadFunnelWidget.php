<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use Filament\Widgets\Widget;

/**
 * Lead-to-won funnel with the drop-off between each step.
 */
class LeadFunnelWidget extends Widget
{
    use UsesReportFilters;

    protected string $view = 'filament.widgets.reports.lead-funnel';

    protected static ?int $sort = 11;

    /**
     * @return list<array{label: string, count: int, percent: int, drop: ?int}>
     */
    public function getSteps(): array
    {
        $funnel = $this->reportFilters()->remember('funnel', fn (): array => $this->metrics()->funnel());
        $first = max(1, reset($funnel) ?: 0);
        $previous = null;
        $steps = [];

        foreach ($funnel as $label => $count) {
            $steps[] = [
                'label' => $label,
                'count' => $count,
                'percent' => (int) round($count / $first * 100),
                'drop' => $previous === null || $previous === 0 ? null : (int) round(($previous - $count) / $previous * 100),
            ];

            $previous = $count;
        }

        return $steps;
    }
}

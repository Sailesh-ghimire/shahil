<?php

namespace App\Filament\Widgets\Reports\Concerns;

use App\Support\Reports\ReportFilters;
use App\Support\Reports\SalesMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Every Reports widget reads the page's filters (date range, owner, pipeline) through here.
 */
trait UsesReportFilters
{
    use InteractsWithPageFilters;

    /**
     * Chart colours that read well in light and dark mode.
     *
     * @var list<string>
     */
    public const PALETTE = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'];

    protected function reportFilters(): ReportFilters
    {
        return ReportFilters::fromPage($this->pageFilters);
    }

    protected function metrics(): SalesMetrics
    {
        return new SalesMetrics($this->reportFilters());
    }

    /**
     * @return list<string>
     */
    protected static function colors(int $count): array
    {
        return array_map(fn (int $i): string => self::PALETTE[$i % count(self::PALETTE)], range(0, max(0, $count - 1)));
    }

    protected static function transparent(string $hex, float $alpha = 0.35): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x') ?? [0, 0, 0];

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view.own') || auth()->user()?->can('reports.view.team');
    }
}

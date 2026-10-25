<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Revenue won, win rate, days to close, open pipeline — with sparklines.
 */
class ReportStatsOverview extends StatsOverviewWidget
{
    use UsesReportFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->reportFilters();

        return $filters->remember('stats', function () use ($filters): array {
            $metrics = $this->metrics();
            $currency = currentTeam()->currency;
            $monthly = array_values($metrics->wonRevenueByMonth(6));
            $winRate = $metrics->winRate();
            $days = $metrics->averageDaysToClose();

            return [
                Stat::make('Revenue won', Money::format($metrics->revenueWon(), $currency))
                    ->description($filters->from->format('j M').' – '.$filters->until->format('j M Y'))
                    ->chart(array_map(fn (int $value): float => $value / 100, $monthly))
                    ->color('success'),
                Stat::make('Win rate', $winRate === null ? '—' : $winRate.'%')
                    ->description('Won ÷ (won + lost), deals closed in the period')
                    ->color($winRate !== null && $winRate < 30 ? 'danger' : 'primary'),
                Stat::make('Days to close', $days === null ? '—' : (string) $days)
                    ->description('Average, from created to won')
                    ->color('info'),
                Stat::make('Open pipeline', Money::format($metrics->openPipelineValue(), $currency))
                    ->description('New deals per week, last 8 weeks')
                    ->chart(array_map(floatval(...), $metrics->dealsCreatedPerWeek()))
                    ->color('primary'),
            ];
        });
    }
}

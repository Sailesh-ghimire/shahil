<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\UsesReportFilters;
use App\Support\Money;
use App\Support\Workspace\WorkspaceSettings;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueTargetChart extends ChartWidget
{
    use UsesReportFilters;

    protected ?string $heading = 'Revenue won vs target';

    protected ?string $description = 'Are we on track?';

    protected static ?int $sort = 2;

    public ?string $filter = '6';

    protected function getFilters(): ?array
    {
        return ['6' => 'Last 6 months', '12' => 'Last 12 months'];
    }

    protected function getData(): array
    {
        $months = $this->filter === '12' ? 12 : 6;
        $filters = $this->reportFilters();

        $revenue = $filters->remember("revenue-{$months}", fn (): array => $this->metrics()->wonRevenueByMonth($months));
        $target = WorkspaceSettings::for(currentTeam())->monthlyRevenueTarget();

        $datasets = [[
            'label' => 'Revenue won (Rs)',
            'data' => array_map(fn (int $value): float => Money::fromMinor($value), array_values($revenue)),
            'borderColor' => self::PALETTE[0],
            'backgroundColor' => self::transparent(self::PALETTE[0], 0.15),
            'fill' => true,
            'tension' => 0.3,
        ]];

        if ($target > 0) {
            $datasets[] = [
                'label' => 'Target (Rs)',
                'data' => array_fill(0, $months, Money::fromMinor($target)),
                'borderColor' => self::PALETTE[7],
                'borderDash' => [6, 6],
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(fn (string $key): string => Carbon::createFromFormat('Y-m', $key)->format('M y'), array_keys($revenue)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

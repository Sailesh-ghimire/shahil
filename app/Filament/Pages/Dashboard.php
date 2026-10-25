<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Dashboard\MyFollowUpsTable;
use App\Filament\Widgets\Dashboard\MyNewLeadsTable;
use App\Filament\Widgets\Dashboard\TodayOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

/**
 * "Who do I contact today?" — a rep's day at a glance.
 */
class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            TodayOverview::class,
            MyFollowUpsTable::class,
            MyNewLeadsTable::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}

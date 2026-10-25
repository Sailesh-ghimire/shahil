<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\LeadStatus;
use App\Enums\StageKind;
use App\Filament\Pages\Inbox;
use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Task;
use App\Support\Money;
use App\Support\Reports\ReportFilters;
use App\Support\Reports\SalesMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TodayOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $userId = auth()->id();
        $tz = auth()->user()->timezone;
        $endOfToday = now($tz)->endOfDay()->utc();
        $startOfToday = now($tz)->startOfDay()->utc();
        $currency = currentTeam()->currency;

        $myTasks = Task::query()->where('assignee_id', $userId)->whereNull('completed_at');
        $dueToday = (clone $myTasks)->whereBetween('due_at', [$startOfToday, $endOfToday])->count();
        $overdue = (clone $myTasks)->where('due_at', '<', $startOfToday)->count();

        $myOpenDeals = Deal::query()->where('owner_id', $userId)->whereNull('closed_at');
        $wonThisMonth = Deal::query()
            ->where('owner_id', $userId)
            ->whereHas('stage', fn (Builder $stage) => $stage->where('kind', StageKind::Won))
            ->where('closed_at', '>=', now($tz)->startOfMonth()->utc());

        $myMetrics = new SalesMetrics(new ReportFilters(now()->subYear(), now(), $userId, null));

        return [
            Stat::make('Follow-ups today', (string) $dueToday)
                ->description($overdue > 0 ? "{$overdue} overdue" : 'Nothing overdue')
                ->descriptionColor($overdue > 0 ? 'danger' : 'success')
                ->url(TaskResource::getUrl())
                ->color('warning'),
            Stat::make('Unread messages', (string) Conversation::query()
                ->where('unread_count', '>', 0)
                ->where(fn (Builder $query) => $query->where('assignee_id', $userId)->orWhereNull('assignee_id'))
                ->count())
                ->description('Assigned to me or unassigned')
                ->url(Inbox::getUrl(['show' => 'unread']))
                ->color('info'),
            Stat::make('New leads for me', (string) Lead::query()->where('owner_id', $userId)->where('status', LeadStatus::New)->count())
                ->description('Not contacted yet')
                ->url(LeadResource::getUrl('board')),
            Stat::make('My open pipeline', Money::format((int) (clone $myOpenDeals)->sum('amount'), $currency))
                ->description((clone $myOpenDeals)->count().' open deals · new per week')
                ->chart(array_map(floatval(...), $myMetrics->dealsCreatedPerWeek()))
                ->url(DealResource::getUrl('board'))
                ->color('primary'),
            Stat::make('Won this month', Money::format((int) $wonThisMonth->sum('amount'), $currency))
                ->description('Last 6 months')
                ->chart(array_map(fn (int $value): float => $value / 100, array_values($myMetrics->wonRevenueByMonth(6))))
                ->color('success'),
        ];
    }
}

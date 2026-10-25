<?php

namespace App\Support\Reports;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Enums\StageKind;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The SQL behind the Reports widgets. Everything is grouped in SQL, not in PHP.
 */
class SalesMetrics
{
    public function __construct(protected ReportFilters $filters) {}

    /**
     * @return Builder<Deal>
     */
    public function deals(): Builder
    {
        return $this->filters->forDeals(Deal::query());
    }

    /**
     * @return Builder<Deal>
     */
    public function closedDeals(StageKind $kind): Builder
    {
        return $this->deals()
            ->whereHas('stage', fn (Builder $stage) => $stage->where('kind', $kind))
            ->whereBetween('deals.closed_at', [$this->filters->from, $this->filters->until]);
    }

    public function revenueWon(): int
    {
        return (int) $this->closedDeals(StageKind::Won)->sum('amount');
    }

    public function winRate(): ?float
    {
        $won = $this->closedDeals(StageKind::Won)->count();
        $lost = $this->closedDeals(StageKind::Lost)->count();

        return ($won + $lost) === 0 ? null : round($won / ($won + $lost) * 100, 1);
    }

    public function averageDaysToClose(): ?float
    {
        $average = $this->closedDeals(StageKind::Won)
            ->selectRaw('avg(timestampdiff(day, deals.created_at, deals.closed_at)) as days')
            ->value('days');

        return $average === null ? null : round((float) $average, 1);
    }

    public function openPipelineValue(): int
    {
        return (int) $this->deals()->whereNull('closed_at')->sum('amount');
    }

    /**
     * Won revenue per month for the last $months months, keyed "2026-10".
     *
     * @return array<string, int>
     */
    public function wonRevenueByMonth(int $months): array
    {
        $keys = ReportFilters::lastMonths($months);

        $rows = $this->deals()
            ->whereHas('stage', fn (Builder $stage) => $stage->where('kind', StageKind::Won))
            ->where('deals.closed_at', '>=', now()->startOfMonth()->subMonths($months - 1))
            ->selectRaw("date_format(deals.closed_at, '%Y-%m') as month, sum(deals.amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => (int) ($rows[$key] ?? 0)])->all();
    }

    /**
     * Deals created per week for the last 8 weeks (sparkline).
     *
     * @return list<int>
     */
    public function dealsCreatedPerWeek(int $weeks = 8): array
    {
        $rows = $this->deals()
            ->where('deals.created_at', '>=', now()->startOfWeek()->subWeeks($weeks - 1))
            ->selectRaw('yearweek(deals.created_at, 3) as week, count(*) as total')
            ->groupBy('week')
            ->pluck('total', 'week');

        return collect(range($weeks - 1, 0))
            ->map(fn (int $ago): int => (int) ($rows[(int) now()->startOfWeek()->subWeeks($ago)->format('oW')] ?? 0))
            ->all();
    }

    /**
     * Open deal value and count per stage of the chosen (or default) pipeline.
     *
     * @return Collection<int, stdClass>
     */
    public function pipelineByStage(): Collection
    {
        return DB::table('stages')
            ->leftJoin('deals', function ($join): void {
                $join->on('deals.stage_id', '=', 'stages.id')
                    ->whereNull('deals.deleted_at')
                    ->whereNull('deals.closed_at');

                if ($this->filters->ownerId !== null) {
                    $join->where('deals.owner_id', $this->filters->ownerId);
                }
            })
            ->where('stages.team_id', currentTeam()->getKey())
            ->where('stages.kind', StageKind::Open->value)
            ->where('stages.pipeline_id', $this->filters->pipelineId ?? DB::table('pipelines')->where('team_id', currentTeam()->getKey())->orderByDesc('is_default')->orderBy('sort')->value('id'))
            ->groupBy('stages.id', 'stages.name', 'stages.sort')
            ->orderBy('stages.sort')
            ->selectRaw('stages.name as name, coalesce(sum(deals.amount), 0) as value, count(deals.id) as deals')
            ->get()
            ->map(fn (object $row): object => (object) ['name' => $row->name, 'value' => (int) $row->value, 'deals' => (int) $row->deals]);
    }

    /**
     * @return array<string, int>
     */
    public function leadSources(): array
    {
        return $this->filters->forOwner(Lead::query())
            ->whereBetween('created_at', [$this->filters->from, $this->filters->until])
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->pluck('total', 'source')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function wonLostByRep(): Collection
    {
        return $this->deals()
            ->join('stages', 'stages.id', '=', 'deals.stage_id')
            ->join('users', 'users.id', '=', 'deals.owner_id')
            ->whereIn('stages.kind', [StageKind::Won->value, StageKind::Lost->value])
            ->whereBetween('deals.closed_at', [$this->filters->from, $this->filters->until])
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw("users.name as name, sum(stages.kind = 'won') as won, sum(stages.kind = 'lost') as lost")
            ->toBase()
            ->get()
            ->map(fn (object $row): object => (object) ['name' => $row->name, 'won' => (int) $row->won, 'lost' => (int) $row->lost]);
    }

    /**
     * @return array<string, int>
     */
    public function lostReasons(): array
    {
        return $this->closedDeals(StageKind::Lost)
            ->selectRaw("coalesce(nullif(lost_reason, ''), 'No reason given') as reason, count(*) as total")
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'reason')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function activityMix(): array
    {
        return $this->filters->forOwner(Activity::query(), 'user_id')
            ->where('type', '!=', ActivityType::StageChange)
            ->whereBetween('happened_at', [$this->filters->from, $this->filters->until])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Per rep, five scores scaled 0–100 against the best rep: activities, deals won, revenue, win rate, leads converted.
     *
     * @return Collection<int, stdClass>
     */
    public function repScorecard(): Collection
    {
        $team = currentTeam();
        $from = $this->filters->from;
        $until = $this->filters->until;

        $reps = $team->members()
            ->when($this->filters->ownerId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($from, $until): object {
                $won = Deal::query()->where('owner_id', $user->id)->whereBetween('closed_at', [$from, $until])->whereHas('stage', fn (Builder $s) => $s->where('kind', StageKind::Won));
                $lost = Deal::query()->where('owner_id', $user->id)->whereBetween('closed_at', [$from, $until])->whereHas('stage', fn (Builder $s) => $s->where('kind', StageKind::Lost))->count();
                $wonCount = (clone $won)->count();

                return (object) [
                    'name' => $user->name,
                    'raw' => [
                        Activity::query()->where('user_id', $user->id)->where('type', '!=', ActivityType::StageChange)->whereBetween('happened_at', [$from, $until])->count(),
                        $wonCount,
                        (int) (clone $won)->sum('amount'),
                        ($wonCount + $lost) === 0 ? 0 : $wonCount / ($wonCount + $lost) * 100,
                        Lead::query()->where('owner_id', $user->id)->where('status', LeadStatus::Converted)->whereBetween('converted_at', [$from, $until])->count(),
                    ],
                ];
            })
            ->filter(fn (object $rep): bool => array_sum($rep->raw) > 0)
            ->values();

        $max = collect(range(0, 4))->map(fn (int $i): float => (float) max(1, $reps->max(fn (object $rep) => $rep->raw[$i])));

        return $reps->map(fn (object $rep): object => (object) [
            'name' => $rep->name,
            'scores' => collect($rep->raw)->map(fn (float|int $value, int $i): float => round($i === 3 ? $value : $value / $max[$i] * 100, 1))->values()->all(),
        ]);
    }

    /**
     * Open deals with value, days idle and win probability (bubble chart), biggest first.
     *
     * @return Collection<int, stdClass>
     */
    public function idleOpenDeals(int $limit = 40): Collection
    {
        return $this->deals()
            ->whereNull('closed_at')
            ->with('stage')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn (Deal $deal): object => (object) [
                'title' => $deal->title,
                'value' => $deal->amount,
                'idle' => $deal->daysIdle(),
                'probability' => $deal->stage->probability,
            ]);
    }

    /**
     * Average minutes from a customer's message to our first reply, per channel.
     *
     * @return array<string, float>
     */
    public function firstReplyMinutesByChannel(): array
    {
        return $this->filters->forOwner(Message::query(), 'sent_by')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('channel_accounts', 'channel_accounts.id', '=', 'conversations.channel_account_id')
            ->where('messages.direction', Message::OUTBOUND)
            ->whereNotNull('messages.response_seconds')
            ->whereBetween('messages.created_at', [$this->filters->from, $this->filters->until])
            ->groupBy('channel_accounts.channel')
            ->selectRaw('channel_accounts.channel as channel, avg(messages.response_seconds) / 60 as minutes')
            ->toBase()
            ->pluck('minutes', 'channel')
            ->map(fn (mixed $minutes): float => round((float) $minutes, 1))
            ->all();
    }

    /**
     * Leads received in the period → contacted → qualified → converted → won.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $leads = $this->filters->forOwner(Lead::query())->whereBetween('created_at', [$this->filters->from, $this->filters->until]);

        $received = (clone $leads)->count();
        $contacted = (clone $leads)->whereIn('status', [LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Converted])->count();
        $qualified = (clone $leads)->whereIn('status', [LeadStatus::Qualified, LeadStatus::Converted])->count();
        $converted = (clone $leads)->where('status', LeadStatus::Converted)->count();
        $won = (clone $leads)
            ->where('status', LeadStatus::Converted)
            ->whereHas('convertedDeal', fn (Builder $deal) => $deal->whereHas('stage', fn (Builder $stage) => $stage->where('kind', StageKind::Won)))
            ->count();

        return ['Leads received' => $received, 'Contacted' => $contacted, 'Qualified' => $qualified, 'Converted to deal' => $converted, 'Won' => $won];
    }
}

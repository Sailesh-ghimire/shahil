<?php

namespace App\Support\Reports;

use App\Models\Deal;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The Reports page filters (date range, owner, pipeline), validated, plus the
 * "reps only see their own numbers" rule. Results are cached for 10 minutes per workspace and filter set.
 */
class ReportFilters
{
    public const CACHE_SECONDS = 600;

    public function __construct(
        public Carbon $from,
        public Carbon $until,
        public ?int $ownerId,
        public ?int $pipelineId,
    ) {}

    /**
     * @param  array<string, mixed>|null  $pageFilters
     */
    public static function fromPage(?array $pageFilters): self
    {
        $pageFilters ??= [];
        $tz = auth()->user()->timezone ?? config('app.timezone');

        $from = static::date($pageFilters['from'] ?? null, $tz) ?? now($tz)->startOfMonth();
        $until = static::date($pageFilters['until'] ?? null, $tz) ?? now($tz);

        if ($from->greaterThan($until)) {
            [$from, $until] = [$until, $from];
        }

        return new self(
            from: $from->copy()->startOfDay()->utc(),
            until: $until->copy()->endOfDay()->utc(),
            ownerId: static::lockedOwnerId() ?? (filled($pageFilters['owner_id'] ?? null) ? (int) $pageFilters['owner_id'] : null),
            pipelineId: filled($pageFilters['pipeline_id'] ?? null) ? (int) $pageFilters['pipeline_id'] : null,
        );
    }

    /**
     * People with only reports.view.own always see their own numbers.
     */
    public static function lockedOwnerId(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->can('reports.view.team') ? null : $user->getKey();
    }

    protected static function date(mixed $value, string $tz): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function forOwner(Builder $query, string $column = 'owner_id'): Builder
    {
        return $query->when($this->ownerId, fn (Builder $query, int $ownerId) => $query->where($query->qualifyColumn($column), $ownerId));
    }

    /**
     * @param  Builder<Deal>  $query
     * @return Builder<Deal>
     */
    public function forDeals(Builder $query): Builder
    {
        return $this->forOwner($query)
            ->when($this->pipelineId, fn (Builder $query, int $pipelineId) => $query->where('deals.pipeline_id', $pipelineId));
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, Closure $callback): mixed
    {
        $teamId = currentTeam()->getKey();
        $hash = md5(json_encode([$this->from->toIso8601String(), $this->until->toIso8601String(), $this->ownerId, $this->pipelineId]) ?: '');

        return Cache::remember("reports:{$teamId}:{$key}:{$hash}", self::CACHE_SECONDS, $callback);
    }

    /**
     * Month keys ("2026-10") from $months ago up to the current month.
     *
     * @return list<string>
     */
    public static function lastMonths(int $months): array
    {
        return collect(range($months - 1, 0))
            ->map(fn (int $ago): string => now()->startOfMonth()->subMonths($ago)->format('Y-m'))
            ->all();
    }
}

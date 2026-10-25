<?php

namespace App\Console\Commands;

use App\Enums\TaskType;
use App\Models\Deal;
use App\Models\Task;
use App\Models\Team;
use App\Support\Workspace\CurrentWorkspace;
use App\Support\Workspace\WorkspaceSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Deal untouched for N days" creates one follow-up for the owner (N is a workspace setting, 0 turns it off).
 */
class CreateStaleDealFollowUps extends Command
{
    protected $signature = 'crm:stale-deal-follow-ups';

    protected $description = 'Create follow-ups for deals with no recent activity';

    public function handle(CurrentWorkspace $workspace): int
    {
        $created = 0;

        foreach (Team::query()->cursor() as $team) {
            $days = WorkspaceSettings::for($team)->staleDealDays();

            if ($days <= 0) {
                continue;
            }

            $created += $workspace->run($team, fn (): int => $this->forWorkspace($team, $days));
        }

        $this->components->info("Created {$created} follow-up(s).");

        return self::SUCCESS;
    }

    protected function forWorkspace(Team $team, int $days): int
    {
        $cutoff = now()->subDays($days);
        $created = 0;

        Deal::query()
            ->open()
            ->whereNotNull('owner_id')
            ->where(fn (Builder $query) => $query
                ->where('last_activity_at', '<', $cutoff)
                ->orWhere(fn (Builder $query) => $query->whereNull('last_activity_at')->where('stage_changed_at', '<', $cutoff)))
            ->whereDoesntHave('tasks', fn (Builder $tasks) => $tasks->whereNull('completed_at'))
            ->each(function (Deal $deal) use ($team, &$created): void {
                $dueAt = now($team->timezone)->setTime(10, 0);

                if ($dueAt->isPast()) {
                    $dueAt = $dueAt->addDay();
                }

                Task::query()->create([
                    'team_id' => $team->getKey(),
                    'subject_type' => $deal->getMorphClass(),
                    'subject_id' => $deal->getKey(),
                    'title' => 'Follow up: '.$deal->title,
                    'type' => TaskType::Call,
                    'notes' => 'No activity for '.$deal->daysIdle().' days.',
                    'due_at' => $dueAt->utc(),
                    'remind_minutes_before' => 0,
                    'assignee_id' => $deal->owner_id,
                    'is_automatic' => true,
                ]);

                $created++;
            });

        return $created;
    }
}

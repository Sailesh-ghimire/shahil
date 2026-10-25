<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Team;
use App\Notifications\TaskReminder;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Console\Command;

/**
 * Runs every minute: sends each due reminder once (bell + email) to the task's assignee.
 */
class SendTaskReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Send follow-up reminders that are due';

    public function handle(CurrentWorkspace $workspace): int
    {
        $sent = 0;

        Task::query()
            ->reminderDue()
            ->whereNotNull('assignee_id')
            ->with(['assignee'])
            ->chunkById(200, function ($tasks) use ($workspace, &$sent): void {
                foreach ($tasks as $task) {
                    $claimed = Task::query()->withoutGlobalScopes()
                        ->whereKey($task->getKey())
                        ->whereNull('reminded_at')
                        ->update(['reminded_at' => now()]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $workspace->run(Team::query()->findOrFail($task->team_id), fn () => $task->assignee?->notify(new TaskReminder($task)));
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}

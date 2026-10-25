<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Mark done" and "Snooze 1 hour" links from reminder notifications and emails (signed URLs).
 */
class TaskReminderController
{
    public function complete(Request $request, int $task): RedirectResponse
    {
        $model = $this->resolve($request, $task);
        $model->complete();

        Notification::make()->title('Task completed')->body(e($model->title))->success()->send();

        return $this->back($model);
    }

    public function snooze(Request $request, int $task): RedirectResponse
    {
        $model = $this->resolve($request, $task);
        $model->snooze(60);

        Notification::make()->title('Snoozed for 1 hour')->body(e($model->title))->info()->send();

        return $this->back($model);
    }

    protected function resolve(Request $request, int $id): Task
    {
        $task = Task::query()->withoutGlobalScopes()->findOrFail($id);
        $user = $request->user();

        abort_unless($user instanceof User && $user->belongsToTeam(Team::query()->findOrFail($task->team_id)), 403);

        setPermissionsTeamId($task->team_id);
        abort_unless($user->can('update', $task), 403);

        return $task;
    }

    protected function back(Task $task): RedirectResponse
    {
        $team = Team::query()->findOrFail($task->team_id);

        return redirect(Filament::getPanel('app')->getUrl($team));
    }
}

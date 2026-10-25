<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pops up reminders as persistent toasts while the panel is open (polls every 30 s;
 * the bell and email are sent by crm:send-reminders either way).
 */
class ReminderToasts extends Component
{
    /**
     * @var list<int>
     */
    public array $shown = [];

    public function mount(): void
    {
        $this->shown = Task::query()
            ->where('assignee_id', auth()->id())
            ->whereNotNull('reminded_at')
            ->whereNull('completed_at')
            ->pluck('id')
            ->all();
    }

    public function check(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $tasks = Task::query()
            ->where('assignee_id', $user->getKey())
            ->whereNotNull('reminded_at')
            ->whereNull('completed_at')
            ->where('reminded_at', '>=', now()->subHour())
            ->whereNotIn('id', $this->shown)
            ->get();

        foreach ($tasks as $task) {
            $this->shown[] = $task->getKey();

            Notification::make("reminder-{$task->getKey()}")
                ->title('Reminder: '.e($task->title))
                ->body(TaskReminder::dueText($task, $user))
                ->warning()
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->persistent()
                ->actions([
                    Action::make('done')->label('Mark done')->button()->dispatch('completeTask', [$task->getKey()])->close(),
                    Action::make('snooze')->label('Snooze 1 hour')->color('gray')->dispatch('snoozeTask', [$task->getKey(), 60])->close(),
                ])
                ->send();
        }
    }

    #[On('completeTask')]
    public function completeTask(int $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);
        $this->authorize('update', $task);
        $task->complete();

        Notification::make()->title('Task completed')->body(e($task->title))->success()->send();
    }

    #[On('snoozeTask')]
    public function snoozeTask(int $taskId, int $minutes = 60): void
    {
        $task = Task::query()->findOrFail($taskId);
        $this->authorize('update', $task);
        $task->snooze(max(5, min($minutes, 1440)));
        $this->shown = array_values(array_diff($this->shown, [$taskId]));

        Notification::make()->title('Snoozed')->body(e($task->title).' — reminder in '.$minutes.' minutes.')->info()->send();
    }

    public function render(): View
    {
        return view('livewire.reminder-toasts');
    }
}

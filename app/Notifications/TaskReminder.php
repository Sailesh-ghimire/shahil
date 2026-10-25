<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Reminder: Call Sita about the proposal" — in the bell and by email.
 */
class TaskReminder extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Reminder: '.e($this->task->title))
            ->body(static::dueText($this->task, $notifiable))
            ->warning()
            ->icon('heroicon-o-bell')
            ->actions([
                Action::make('done')
                    ->label('Mark done')
                    ->button()
                    ->url(static::completeUrl($this->task))
                    ->markAsRead(),
                Action::make('snooze')
                    ->label('Snooze 1 hour')
                    ->color('gray')
                    ->url(static::snoozeUrl($this->task))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: '.$this->task->title)
            ->line(static::dueText($this->task, $notifiable))
            ->when($this->task->notes, fn (MailMessage $mail) => $mail->line($this->task->notes))
            ->action('Mark done', static::completeUrl($this->task))
            ->line('Need more time? Snooze it for an hour: '.static::snoozeUrl($this->task));
    }

    public static function dueText(Task $task, User $user): string
    {
        $due = $task->due_at->copy()->tz($user->timezone);
        $day = $due->isToday() ? 'today' : ($due->isTomorrow() ? 'tomorrow' : $due->format('D j M'));

        return "Due {$day} at {$due->format('H:i')}.";
    }

    public static function completeUrl(Task $task): string
    {
        return URL::temporarySignedRoute('tasks.complete', now()->addDays(7), ['task' => $task->getKey()]);
    }

    public static function snoozeUrl(Task $task): string
    {
        return URL::temporarySignedRoute('tasks.snooze', now()->addDays(7), ['task' => $task->getKey()]);
    }
}

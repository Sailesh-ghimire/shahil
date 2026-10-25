<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;

/**
 * Emailed to OPS_ALERT_EMAIL when a background job (webhook, send, import, reminder) gives up.
 */
class JobFailedAlert extends Notification
{
    public function __construct(
        public string $jobName,
        public string $queue,
        public string $error,
    ) {}

    public static function fromEvent(JobFailed $event): self
    {
        return new self(
            jobName: $event->job->resolveName(),
            queue: $event->job->getQueue(),
            error: Str::limit($event->exception::class.': '.$event->exception->getMessage(), 500),
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('[Workspace CRM] Job failed: '.class_basename($this->jobName))
            ->line("Job: {$this->jobName}")
            ->line("Queue: {$this->queue}")
            ->line("Error: {$this->error}")
            ->line('Retry it from Horizon (/horizon/failed) once the cause is fixed.');
    }
}

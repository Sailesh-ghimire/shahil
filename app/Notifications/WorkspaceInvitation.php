<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token  The plain token; only its hash is stored.
     */
    public function __construct(public TeamInvitation $invitation, public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter()->value('name') ?? 'A colleague';

        return (new MailMessage)
            ->subject("Join {$team->name} on Workspace CRM")
            ->line("{$inviter} invited you to the {$team->name} workspace as {$this->invitation->role?->name}.")
            ->action('Accept invitation', route('invitations.accept', $this->token))
            ->line('The link expires on '.$this->invitation->expires_at->toFormattedDayDateString().'.');
    }
}

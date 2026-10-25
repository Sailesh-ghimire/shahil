<?php

namespace App\Notifications;

use App\Filament\Pages\Channels;
use App\Filament\Pages\Inbox;
use App\Integrations\Meta\MetaApiException;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Danger, persistent: the reply didn't go out. Points to Reconnect or Send template.
 */
class MessageNotDelivered extends Notification
{
    public function __construct(public Message $message, public MetaApiException $exception) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $team = Team::query()->findOrFail($this->message->team_id);
        $conversation = $this->message->conversation;

        $action = match (true) {
            $this->exception->isAuthError() => Action::make('reconnect')->label('Reconnect')->button()->url(Channels::getUrl(tenant: $team)),
            $this->exception->isOutsideReplyWindow() => Action::make('template')->label('Send template')->button()->url(Inbox::getUrl(['conversation' => $conversation->getKey()], tenant: $team)),
            default => Action::make('open')->label('Open conversation')->button()->url(Inbox::getUrl(['conversation' => $conversation->getKey()], tenant: $team)),
        };

        return FilamentNotification::make()
            ->title('Message not delivered')
            ->body('To '.e($conversation->title()).': '.e($this->exception->getMessage()))
            ->danger()
            ->actions([$action->markAsRead()])
            ->getDatabaseMessage();
    }
}

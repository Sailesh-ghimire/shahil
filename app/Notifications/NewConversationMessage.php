<?php

namespace App\Notifications;

use App\Filament\Pages\Inbox;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * "New message from Dawa Sherpa" in the bell, for the assignee (or everyone who can assign chats).
 */
class NewConversationMessage extends Notification
{
    public function __construct(public Conversation $conversation, public Message $message, public bool $isNewSender) {}

    public static function sendFor(Conversation $conversation, Message $message, bool $isNewSender): void
    {
        $recipients = $conversation->assignee !== null
            ? collect([$conversation->assignee])
            : static::assigners($conversation);

        $recipients->each(fn (User $user) => $user->notify(new self($conversation, $message, $isNewSender)));
    }

    /**
     * @return Collection<int, User>
     */
    protected static function assigners(Conversation $conversation): Collection
    {
        $team = Team::query()->findOrFail($conversation->team_id);

        return app(CurrentWorkspace::class)->run($team, fn () => $team->members()->get()->filter(function (User $user): bool {
            $user->forgetCachedPermissions();

            return $user->hasPermissionTo('inbox.assign');
        })->values());
    }

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
        $channel = $this->conversation->channelAccount->channel->getLabel();
        $team = Team::query()->findOrFail($this->conversation->team_id);
        $url = Inbox::getUrl(['conversation' => $this->conversation->getKey()], tenant: $team);

        return FilamentNotification::make()
            ->title(($this->isNewSender ? 'New '.$channel.' message from ' : 'Message from ').e($this->conversation->title()))
            ->body(e($this->message->preview()))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('info')
            ->actions([
                Action::make('open')->label('Open conversation')->button()->url($url)->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}

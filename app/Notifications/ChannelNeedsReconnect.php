<?php

namespace App\Notifications;

use App\Filament\Pages\Channels;
use App\Models\ChannelAccount;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChannelNeedsReconnect extends Notification
{
    public function __construct(public ChannelAccount $account) {}

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
            ->title($this->account->channel->getLabel().' needs reconnecting')
            ->body(e($this->account->name).': '.e((string) $this->account->status_message))
            ->danger()
            ->actions([
                Action::make('reconnect')->label('Reconnect')->button()->url($this->url())->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->account->channel->getLabel().' needs reconnecting')
            ->line("{$this->account->name} can no longer send or receive messages: {$this->account->status_message}")
            ->action('Reconnect', $this->url());
    }

    protected function url(): string
    {
        return Channels::getUrl(tenant: Team::query()->findOrFail($this->account->team_id));
    }
}

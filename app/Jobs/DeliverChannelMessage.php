<?php

namespace App\Jobs;

use App\Enums\ChannelAccountStatus;
use App\Enums\MessageStatus;
use App\Integrations\Meta\ChannelDrivers;
use App\Integrations\Meta\MetaApiException;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppTemplate;
use App\Notifications\MessageNotDelivered;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a queued reply through the Graph API and stores Meta's message id.
 */
class DeliverChannelMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public int $messageId,
        public ?int $templateId = null,
        public array $parameters = [],
    ) {
        $this->onQueue('messages');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ChannelDrivers $drivers, CurrentWorkspace $workspace): void
    {
        $message = Message::query()->withoutGlobalScope('workspace')->with('conversation.channelAccount')->find($this->messageId);

        if ($message === null || $message->status !== MessageStatus::Queued) {
            return;
        }

        $workspace->run(Team::query()->findOrFail($message->team_id), function () use ($message, $drivers): void {
            $conversation = $message->conversation;
            $account = $conversation->channelAccount;
            $driver = $drivers->for($account->channel);

            try {
                $externalId = $this->templateId === null
                    ? $driver->sendText($account, $conversation, (string) $message->body)
                    : $driver->sendTemplate($account, $conversation, WhatsAppTemplate::query()->findOrFail($this->templateId), $this->parameters);
            } catch (MetaApiException $exception) {
                $this->markFailed($message, $exception);

                return;
            }

            $message->forceFill(['external_id' => $externalId, 'status' => MessageStatus::Sent, 'sent_at' => now()])->save();

            $conversation->timelineSubject()?->logActivity(
                type: $account->channel->activityType(),
                body: $message->preview(),
                user: $message->sender,
                title: 'Reply sent',
            );
        });
    }

    protected function markFailed(Message $message, MetaApiException $exception): void
    {
        $message->forceFill(['status' => MessageStatus::Failed, 'error' => $exception->getMessage()])->save();

        if ($exception->isAuthError()) {
            $message->conversation->channelAccount->forceFill([
                'status' => ChannelAccountStatus::ReconnectNeeded,
                'status_message' => $exception->getMessage(),
            ])->save();
        }

        $message->sender?->notify(new MessageNotDelivered($message, $exception));
    }
}

<?php

namespace App\Jobs;

use App\Actions\Messaging\ReceiveInboundMessage;
use App\Enums\Channel;
use App\Integrations\Meta\ChannelDrivers;
use App\Integrations\Meta\Data\InboundMessage;
use App\Integrations\Meta\Data\StatusUpdate;
use App\Models\ChannelAccount;
use App\Models\Message;
use App\Models\Team;
use App\Models\WebhookLog;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Turns a stored webhook payload into messages and status updates, each inside the right workspace
 * (found from the receiving account's id).
 */
class ProcessMetaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $webhookLogId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(ChannelDrivers $drivers, ReceiveInboundMessage $receive, CurrentWorkspace $workspace): void
    {
        $log = WebhookLog::query()->find($this->webhookLogId);

        if ($log === null || $log->processed_at !== null) {
            return;
        }

        $channel = Channel::from($log->channel);

        try {
            foreach ($drivers->for($channel)->parseWebhook($log->payload) as $event) {
                $account = ChannelAccount::findForWebhook($channel, $event->accountExternalId);

                if ($account === null) {
                    continue;
                }

                $workspace->run(Team::query()->findOrFail($account->team_id), function () use ($event, $account, $receive): void {
                    $event instanceof InboundMessage
                        ? $receive->handle($account, $event)
                        : $this->applyStatus($event);
                });
            }

            $log->forceFill(['processed_at' => now(), 'error' => null])->save();
        } catch (Throwable $exception) {
            $log->forceFill(['error' => str($exception->getMessage())->limit(250)->toString()])->save();

            throw $exception;
        }
    }

    protected function applyStatus(StatusUpdate $update): void
    {
        Message::query()
            ->where('external_id', $update->externalMessageId)
            ->where('direction', Message::OUTBOUND)
            ->first()
            ?->applyStatus($update->status, $update->at, $update->error);
    }
}

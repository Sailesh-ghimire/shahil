<?php

namespace App\Integrations\Meta\Drivers;

use App\Enums\MessageStatus;
use App\Integrations\Meta\Contracts\ChannelDriver;
use App\Integrations\Meta\Data\InboundMessage;
use App\Integrations\Meta\Data\StatusUpdate;
use App\Integrations\Meta\Data\TokenStatus;
use App\Integrations\Meta\GraphClient;
use App\Integrations\Meta\MetaApiException;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Messenger for Facebook Pages. The account's external_id is the Page id; the thread id is the customer's PSID.
 */
class MessengerDriver implements ChannelDriver
{
    public function __construct(protected GraphClient $graph) {}

    public function parseWebhook(array $payload): array
    {
        $events = [];

        foreach (Arr::wrap($payload['entry'] ?? []) as $entry) {
            $accountId = (string) ($entry['id'] ?? '');

            foreach (Arr::wrap($entry['messaging'] ?? []) as $event) {
                $senderId = (string) ($event['sender']['id'] ?? '');
                $at = Carbon::createFromTimestampMs((int) ($event['timestamp'] ?? now()->getTimestampMs()));

                if (isset($event['message']) && ! ($event['message']['is_echo'] ?? false) && $senderId !== $accountId) {
                    $events[] = $this->inboundMessage($accountId, $senderId, $event['message'], $at);
                }

                foreach (Arr::wrap($event['delivery']['mids'] ?? []) as $mid) {
                    $events[] = new StatusUpdate($accountId, (string) $mid, MessageStatus::Delivered, $at);
                }

                if (isset($event['read'])) {
                    foreach ($this->unreadOutboundIds($accountId, $senderId, $event['read']) as $mid) {
                        $events[] = new StatusUpdate($accountId, $mid, MessageStatus::Read, $at);
                    }
                }
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function inboundMessage(string $accountId, string $senderId, array $message, Carbon $at): InboundMessage
    {
        $attachment = $message['attachments'][0] ?? null;
        $type = match ($attachment['type'] ?? null) {
            'image' => 'image',
            'audio' => 'audio',
            'video' => 'video',
            'file' => 'document',
            null => 'text',
            default => 'text',
        };

        return new InboundMessage(
            accountExternalId: $accountId,
            threadId: $senderId,
            externalMessageId: (string) $message['mid'],
            type: $type,
            body: $message['text'] ?? null,
            sentAt: $at,
            // Only real media attachments are downloaded; shares, templates and fallbacks can carry any URL.
            mediaUrl: in_array($attachment['type'] ?? null, ['image', 'audio', 'video', 'file'], true) ? ($attachment['payload']['url'] ?? null) : null,
        );
    }

    /**
     * Messenger read receipts carry a watermark (time), not message ids: everything we sent before it was read.
     *
     * @param  array<string, mixed>  $read
     * @return list<string>
     */
    protected function unreadOutboundIds(string $accountId, string $senderId, array $read): array
    {
        $watermark = isset($read['watermark']) ? Carbon::createFromTimestampMs((int) $read['watermark']) : now();

        return Message::query()
            ->withoutGlobalScope('workspace')
            ->where('direction', Message::OUTBOUND)
            ->whereNull('read_at')
            ->whereNotNull('external_id')
            ->where('created_at', '<=', $watermark)
            ->whereHas('conversation', fn ($query) => $query
                ->withoutGlobalScope('workspace')
                ->where('external_thread_id', $senderId)
                ->whereHas('channelAccount', fn ($account) => $account->withoutGlobalScope('workspace')->where('external_id', $accountId)))
            ->pluck('external_id')
            ->all();
    }

    public function sendText(ChannelAccount $account, Conversation $conversation, string $text): string
    {
        $body = [
            'recipient' => ['id' => $conversation->external_thread_id],
            'message' => ['text' => $text],
            'messaging_type' => 'RESPONSE',
        ];

        // Outside the 24-hour window a human agent may still answer for up to 7 days with this tag.
        if (! $conversation->isWithinReplyWindow()) {
            $body['messaging_type'] = 'MESSAGE_TAG';
            $body['tag'] = 'HUMAN_AGENT';
        }

        $response = $this->graph->post($account->access_token, "{$account->external_id}/messages", $body);

        return (string) ($response['message_id'] ?? throw new MetaApiException('Meta did not return a message id.'));
    }

    public function sendTemplate(ChannelAccount $account, Conversation $conversation, WhatsAppTemplate $template, array $parameters): string
    {
        throw new MetaApiException('Templates are only available on WhatsApp.');
    }

    public function downloadMedia(ChannelAccount $account, ?string $mediaId, ?string $mediaUrl): array
    {
        if ($mediaUrl === null) {
            throw new MetaApiException('No media URL.');
        }

        return ['contents' => $this->graph->download($account->access_token, $mediaUrl), 'mime' => null];
    }

    public function fetchSenderName(ChannelAccount $account, string $threadId): ?string
    {
        try {
            $profile = $this->graph->get($account->access_token, $threadId, ['fields' => 'first_name,last_name']);
        } catch (MetaApiException) {
            return null;
        }

        return trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')) ?: null;
    }

    public function checkToken(ChannelAccount $account): TokenStatus
    {
        return MetaTokenInspector::inspect($this->graph, $account, $account->external_id.'?fields=name');
    }

    public function supportsTemplates(): bool
    {
        return false;
    }
}

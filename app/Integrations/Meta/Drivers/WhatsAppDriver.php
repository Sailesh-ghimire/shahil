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
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * WhatsApp Cloud API. The account's external_id is the phone number id; the thread id is the customer's wa_id.
 */
class WhatsAppDriver implements ChannelDriver
{
    /**
     * @var list<string>
     */
    protected const MEDIA_TYPES = ['image', 'audio', 'video', 'document', 'sticker'];

    public function __construct(protected GraphClient $graph) {}

    public function parseWebhook(array $payload): array
    {
        $events = [];

        foreach (Arr::wrap($payload['entry'] ?? []) as $entry) {
            foreach (Arr::wrap($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                if ($phoneNumberId === '') {
                    continue;
                }

                $names = collect((array) ($value['contacts'] ?? []))->mapWithKeys(fn (array $contact): array => [
                    (string) ($contact['wa_id'] ?? '') => $contact['profile']['name'] ?? null,
                ]);

                foreach (Arr::wrap($value['messages'] ?? []) as $message) {
                    $events[] = $this->inboundMessage($phoneNumberId, $message, $names->all());
                }

                foreach (Arr::wrap($value['statuses'] ?? []) as $status) {
                    $mapped = MessageStatus::tryFrom((string) ($status['status'] ?? ''));

                    if ($mapped === null) {
                        continue;
                    }

                    $events[] = new StatusUpdate(
                        accountExternalId: $phoneNumberId,
                        externalMessageId: (string) $status['id'],
                        status: $mapped,
                        at: Carbon::createFromTimestamp((int) ($status['timestamp'] ?? time())),
                        error: $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? null,
                    );
                }
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, ?string>  $names
     */
    protected function inboundMessage(string $phoneNumberId, array $message, array $names): InboundMessage
    {
        $type = (string) ($message['type'] ?? 'text');
        $from = (string) $message['from'];
        $media = in_array($type, self::MEDIA_TYPES, true) ? ($message[$type] ?? []) : [];

        $body = match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null,
            'location' => trim(($message['location']['name'] ?? 'Location').' '.($message['location']['latitude'] ?? '').','.($message['location']['longitude'] ?? '')),
            default => $media['caption'] ?? null,
        };

        return new InboundMessage(
            accountExternalId: $phoneNumberId,
            threadId: $from,
            externalMessageId: (string) $message['id'],
            type: in_array($type, [...self::MEDIA_TYPES, 'text', 'location'], true) ? $type : 'text',
            body: $body,
            sentAt: Carbon::createFromTimestamp((int) ($message['timestamp'] ?? time())),
            senderName: $names[$from] ?? null,
            senderPhone: '+'.ltrim($from, '+'),
            mediaId: $media['id'] ?? null,
            mediaMime: $media['mime_type'] ?? null,
        );
    }

    public function sendText(ChannelAccount $account, Conversation $conversation, string $text): string
    {
        $response = $this->graph->post($account->access_token, "{$account->external_id}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $conversation->external_thread_id,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);

        return (string) ($response['messages'][0]['id'] ?? throw new MetaApiException('Meta did not return a message id.'));
    }

    public function sendTemplate(ChannelAccount $account, Conversation $conversation, WhatsAppTemplate $template, array $parameters): string
    {
        $components = $parameters === [] ? [] : [[
            'type' => 'body',
            'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $parameters),
        ]];

        $response = $this->graph->post($account->access_token, "{$account->external_id}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->external_thread_id,
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => ['code' => $template->language],
                'components' => $components,
            ],
        ]);

        return (string) ($response['messages'][0]['id'] ?? throw new MetaApiException('Meta did not return a message id.'));
    }

    public function downloadMedia(ChannelAccount $account, ?string $mediaId, ?string $mediaUrl): array
    {
        $mime = null;

        if ($mediaId !== null) {
            $info = $this->graph->get($account->access_token, $mediaId);
            $mediaUrl = $info['url'] ?? null;
            $mime = $info['mime_type'] ?? null;
        }

        if ($mediaUrl === null) {
            throw new MetaApiException('No media URL.');
        }

        return ['contents' => $this->graph->download($account->access_token, $mediaUrl), 'mime' => $mime];
    }

    public function fetchSenderName(ChannelAccount $account, string $threadId): ?string
    {
        return null;
    }

    public function checkToken(ChannelAccount $account): TokenStatus
    {
        return MetaTokenInspector::inspect($this->graph, $account, $account->external_id.'?fields=display_phone_number,verified_name');
    }

    public function supportsTemplates(): bool
    {
        return true;
    }

    /**
     * Pulls approved templates from the WhatsApp Business Account.
     *
     * @return list<array{name: string, language: string, category: ?string, status: string, body: ?string}>
     */
    public function fetchTemplates(ChannelAccount $account): array
    {
        if (blank($account->business_account_id)) {
            throw new MetaApiException('Add the WhatsApp Business Account id to sync templates.');
        }

        $response = $this->graph->get($account->access_token, "{$account->business_account_id}/message_templates", [
            'fields' => 'name,language,category,status,components',
            'limit' => 200,
        ]);

        return collect((array) ($response['data'] ?? []))
            ->map(fn (array $template): array => [
                'name' => (string) $template['name'],
                'language' => (string) $template['language'],
                'category' => $template['category'] ?? null,
                'status' => (string) ($template['status'] ?? 'UNKNOWN'),
                'body' => collect((array) ($template['components'] ?? []))->firstWhere('type', 'BODY')['text'] ?? null,
            ])
            ->values()
            ->all();
    }
}

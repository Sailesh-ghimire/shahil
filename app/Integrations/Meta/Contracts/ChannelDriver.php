<?php

namespace App\Integrations\Meta\Contracts;

use App\Integrations\Meta\Data\InboundMessage;
use App\Integrations\Meta\Data\StatusUpdate;
use App\Integrations\Meta\Data\TokenStatus;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\WhatsAppTemplate;

/**
 * One implementation per messaging channel. Adding Telegram or email later is a new driver, not a rewrite.
 */
interface ChannelDriver
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(array $payload): array;

    /**
     * Sends a free-form text reply and returns Meta's message id.
     */
    public function sendText(ChannelAccount $account, Conversation $conversation, string $text): string;

    /**
     * @param  list<string>  $parameters
     */
    public function sendTemplate(ChannelAccount $account, Conversation $conversation, WhatsAppTemplate $template, array $parameters): string;

    /**
     * @return array{contents: string, mime: ?string}
     */
    public function downloadMedia(ChannelAccount $account, ?string $mediaId, ?string $mediaUrl): array;

    public function fetchSenderName(ChannelAccount $account, string $threadId): ?string;

    public function checkToken(ChannelAccount $account): TokenStatus;

    public function supportsTemplates(): bool;
}

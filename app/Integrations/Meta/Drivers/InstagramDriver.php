<?php

namespace App\Integrations\Meta\Drivers;

use App\Integrations\Meta\Data\TokenStatus;
use App\Integrations\Meta\MetaApiException;
use App\Models\ChannelAccount;
use App\Models\Conversation;

/**
 * Instagram messaging. Same webhook shape as Messenger (object "instagram"); the account's external_id is
 * the Instagram professional account id, the thread id the Instagram-scoped user id.
 */
class InstagramDriver extends MessengerDriver
{
    public function sendText(ChannelAccount $account, Conversation $conversation, string $text): string
    {
        $body = [
            'recipient' => ['id' => $conversation->external_thread_id],
            'message' => ['text' => $text],
        ];

        if (! $conversation->isWithinReplyWindow()) {
            $body['messaging_type'] = 'MESSAGE_TAG';
            $body['tag'] = 'HUMAN_AGENT';
        }

        $response = $this->graph->post($account->access_token, "{$account->external_id}/messages", $body);

        return (string) ($response['message_id'] ?? throw new MetaApiException('Meta did not return a message id.'));
    }

    public function fetchSenderName(ChannelAccount $account, string $threadId): ?string
    {
        try {
            $profile = $this->graph->get($account->access_token, $threadId, ['fields' => 'name,username']);
        } catch (MetaApiException) {
            return null;
        }

        return $profile['name'] ?? (isset($profile['username']) ? '@'.$profile['username'] : null);
    }

    public function checkToken(ChannelAccount $account): TokenStatus
    {
        return MetaTokenInspector::inspect($this->graph, $account, $account->external_id.'?fields=username');
    }
}

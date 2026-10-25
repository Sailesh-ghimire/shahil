<?php

namespace App\Actions\Messaging;

use App\Enums\MessageStatus;
use App\Jobs\DeliverChannelMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use InvalidArgumentException;

/**
 * Queues a reply. Free text only inside the 24-hour window on WhatsApp; after that, an approved template.
 * Records how long the customer waited for this reply (first reply time report).
 */
class SendChannelMessage
{
    public function text(Conversation $conversation, User $sender, string $text): Message
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException('Type a message first.');
        }

        if (! $conversation->channelAccount->isUsable()) {
            throw new InvalidArgumentException('This channel needs to be reconnected before you can reply.');
        }

        if ($conversation->channel()->value === 'whatsapp' && ! $conversation->isWithinReplyWindow()) {
            throw new InvalidArgumentException('More than 24 hours since the customer’s last message. Send an approved template instead.');
        }

        return $this->queue($conversation, $sender, ['type' => 'text', 'body' => $text]);
    }

    /**
     * @param  list<string>  $parameters
     */
    public function template(Conversation $conversation, User $sender, WhatsAppTemplate $template, array $parameters): Message
    {
        if ($template->channel_account_id !== $conversation->channel_account_id) {
            throw new InvalidArgumentException('That template belongs to another WhatsApp number.');
        }

        if (count($parameters) < $template->parameter_count) {
            throw new InvalidArgumentException('Fill in every placeholder in the template.');
        }

        return $this->queue($conversation, $sender, [
            'type' => 'template',
            'template_name' => $template->name,
            'body' => $template->render($parameters),
        ], ['template_id' => $template->getKey(), 'parameters' => $parameters]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{template_id?: int, parameters?: list<string>}  $template
     */
    protected function queue(Conversation $conversation, User $sender, array $attributes, array $template = []): Message
    {
        $message = $conversation->messages()->create([
            ...$attributes,
            'team_id' => $conversation->team_id,
            'direction' => Message::OUTBOUND,
            'status' => MessageStatus::Queued,
            'sent_by' => $sender->getKey(),
            'response_seconds' => $conversation->awaiting_reply_since === null
                ? null
                : (int) $conversation->awaiting_reply_since->diffInSeconds(now()),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_preview' => $message->preview(),
            'awaiting_reply_since' => null,
            'unread_count' => 0,
            'assignee_id' => $conversation->assignee_id ?? $sender->getKey(),
        ])->save();

        DeliverChannelMessage::dispatch($message->getKey(), $template['template_id'] ?? null, $template['parameters'] ?? []);

        return $message;
    }
}

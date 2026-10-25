<?php

namespace Tests\Support;

/**
 * Webhook payloads shaped like the ones Meta sends (WhatsApp Cloud API, Messenger, Instagram).
 */
class MetaPayloads
{
    /**
     * @return array<string, mixed>
     */
    public static function whatsAppText(string $phoneNumberId, string $from, string $body, string $id = 'wamid.IN1', string $name = 'Nabin KC'): array
    {
        return static::whatsApp($phoneNumberId, [
            'contacts' => [['profile' => ['name' => $name], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $body]]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function whatsAppImage(string $phoneNumberId, string $from, string $mediaId, string $id = 'wamid.IMG1'): array
    {
        return static::whatsApp($phoneNumberId, [
            'contacts' => [['profile' => ['name' => 'Nabin KC'], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'image', 'image' => ['id' => $mediaId, 'mime_type' => 'image/jpeg', 'caption' => 'Our shop']]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function whatsAppStatus(string $phoneNumberId, string $messageId, string $status): array
    {
        return static::whatsApp($phoneNumberId, [
            'statuses' => [['id' => $messageId, 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '9779811234567']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    protected static function whatsApp(string $phoneNumberId, array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['messaging_product' => 'whatsapp', 'metadata' => ['display_phone_number' => '9779800000000', 'phone_number_id' => $phoneNumberId], ...$value],
                ]],
            ]],
        ];
    }

    /**
     * Messenger (object "page") or Instagram (object "instagram").
     *
     * @return array<string, mixed>
     */
    public static function messaging(string $object, string $accountId, string $senderId, string $text, string $mid = 'm_1'): array
    {
        return [
            'object' => $object,
            'entry' => [[
                'id' => $accountId,
                'time' => now()->getTimestampMs(),
                'messaging' => [[
                    'sender' => ['id' => $senderId],
                    'recipient' => ['id' => $accountId],
                    'timestamp' => now()->getTimestampMs(),
                    'message' => ['mid' => $mid, 'text' => $text],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function signedHeaders(array $payload, string $secret = 'test-secret'): array
    {
        return ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', (string) json_encode($payload), $secret)];
    }
}

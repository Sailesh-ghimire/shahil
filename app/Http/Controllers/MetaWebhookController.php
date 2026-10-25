<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Jobs\ProcessMetaWebhook;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One endpoint per channel. Checks Meta's signature, stores the raw payload, queues it and answers 200
 * straight away (Meta retries slow or failed deliveries).
 */
class MetaWebhookController
{
    /**
     * Meta's subscription check: echo hub.challenge when the verify token matches.
     */
    public function verify(Request $request, string $channel): Response
    {
        Channel::from($channel);

        $token = (string) config('services.meta.verify_token');

        if ($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, string $channel): JsonResponse
    {
        $channel = Channel::from($channel);
        $raw = $request->getContent();

        // Unsigned requests are refused before anything is stored.
        if (! static::signatureIsValid($raw, (string) $request->header('X-Hub-Signature-256'))) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = json_decode($raw, true);

        $log = WebhookLog::query()->create([
            'channel' => $channel->value,
            'payload' => is_array($payload) ? $payload : ['raw' => mb_substr($raw, 0, 10_000)],
            'signature_valid' => true,
        ]);

        ProcessMetaWebhook::dispatch($log->getKey());

        return response()->json(['ok' => true]);
    }

    public static function signatureIsValid(string $raw, string $header): bool
    {
        $secret = (string) config('services.meta.app_secret');

        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $header);
    }
}

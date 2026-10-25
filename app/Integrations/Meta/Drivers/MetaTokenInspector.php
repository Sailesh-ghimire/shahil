<?php

namespace App\Integrations\Meta\Drivers;

use App\Integrations\Meta\Data\TokenStatus;
use App\Integrations\Meta\GraphClient;
use App\Integrations\Meta\MetaApiException;
use App\Models\ChannelAccount;
use Illuminate\Support\Carbon;

/**
 * Token health: debug_token with the app credentials when configured, otherwise a cheap read with the token itself.
 */
class MetaTokenInspector
{
    public static function inspect(GraphClient $graph, ChannelAccount $account, string $probePath): TokenStatus
    {
        $appId = config('services.meta.app_id');
        $appSecret = config('services.meta.app_secret');

        try {
            if (filled($appId) && filled($appSecret)) {
                $data = $graph->get("{$appId}|{$appSecret}", 'debug_token', ['input_token' => $account->access_token])['data'] ?? [];
                $expiresAt = (int) ($data['expires_at'] ?? 0);

                return new TokenStatus(
                    isValid: (bool) ($data['is_valid'] ?? false),
                    expiresAt: $expiresAt > 0 ? Carbon::createFromTimestamp($expiresAt) : null,
                    message: ($data['is_valid'] ?? false) ? null : ($data['error']['message'] ?? 'Token is no longer valid.'),
                );
            }

            $graph->get($account->access_token, $probePath);

            return new TokenStatus(isValid: true);
        } catch (MetaApiException $exception) {
            return new TokenStatus(isValid: ! $exception->isAuthError(), message: $exception->getMessage());
        }
    }
}

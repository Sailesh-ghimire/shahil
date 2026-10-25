<?php

namespace App\Integrations\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin Graph API client on Laravel's HTTP client (no third-party SDK that lags behind Graph versions).
 */
class GraphClient
{
    public function url(string $path): string
    {
        return rtrim((string) config('services.meta.graph_url'), '/').'/'.config('services.meta.graph_version').'/'.ltrim($path, '/');
    }

    public function request(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $token, string $path, array $query = []): array
    {
        return $this->decode($this->request($token)->get($this->url($path), $query));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $token, string $path, array $body): array
    {
        return $this->decode($this->request($token)->post($this->url($path), $body));
    }

    /**
     * Hosts Meta serves chat media from. Anything else is refused (the URL comes from a webhook payload).
     *
     * @var list<string>
     */
    public const MEDIA_HOSTS = ['fbcdn.net', 'fbsbx.com', 'cdninstagram.com', 'facebook.com', 'whatsapp.net'];

    /**
     * Hosts that may receive the account's access token (WhatsApp media needs it; CDN links don't).
     *
     * @var list<string>
     */
    public const TOKEN_HOSTS = ['graph.facebook.com', 'lookaside.fbsbx.com'];

    /**
     * Downloads a file from a (short-lived) Meta media URL. HTTPS on Meta hosts only, no redirects.
     */
    public function download(string $token, string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! static::hostMatches($host, self::MEDIA_HOSTS)) {
            throw new MetaApiException('Refused to download media from '.($host ?: 'an invalid URL').'.');
        }

        $request = in_array($host, self::TOKEN_HOSTS, true)
            ? Http::withToken($token)
            : Http::withHeaders([]);

        $response = $request->withOptions(['allow_redirects' => false])->timeout(30)->get($url);

        if ($response->failed() || $response->redirect()) {
            throw new MetaApiException('Media download failed.', httpStatus: $response->status());
        }

        return $response->body();
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        if ($response->failed() || isset($json['error'])) {
            $error = $json['error'] ?? [];

            throw new MetaApiException(
                (string) ($error['error_user_msg'] ?? $error['message'] ?? 'Meta request failed with HTTP '.$response->status()),
                isset($error['code']) ? (int) $error['code'] : null,
                $response->status(),
            );
        }

        return is_array($json) ? $json : [];
    }
}

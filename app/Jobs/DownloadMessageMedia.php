<?php

namespace App\Jobs;

use App\Integrations\Meta\ChannelDrivers;
use App\Integrations\Meta\MetaApiException;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a photo, voice note or document to our storage: Meta's media links expire.
 */
class DownloadMessageMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Media links from Meta can't be fetched more than 25 MB at a time on WhatsApp; bigger files are skipped.
     */
    public const MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(public int $messageId)
    {
        $this->onQueue('media');
    }

    public function handle(ChannelDrivers $drivers): void
    {
        $message = Message::query()->withoutGlobalScope('workspace')->with('conversation.channelAccount')->find($this->messageId);

        if ($message === null || $message->media_path !== null) {
            return;
        }

        $account = $message->conversation->channelAccount;

        try {
            $file = $drivers->for($account->channel)->downloadMedia($account, $message->media_external_id, $message->media_url);
        } catch (MetaApiException $exception) {
            $message->forceFill(['error' => 'Media not downloaded: '.$exception->getMessage()])->save();

            return;
        }

        if (strlen($file['contents']) > self::MAX_BYTES) {
            $message->forceFill(['error' => 'Media larger than 25 MB was not stored.'])->save();

            return;
        }

        $mime = $file['mime'] ?? $message->media_mime ?? 'application/octet-stream';
        $extension = Str::of($mime)->after('/')->before(';')->replace(['jpeg', 'mpeg', 'ogg; codecs=opus'], ['jpg', 'mp3', 'ogg'])->limit(5, '')->toString();
        $path = "media/{$message->team_id}/{$message->conversation_id}/{$message->getKey()}.{$extension}";

        Storage::disk('local')->put($path, $file['contents']);

        $message->forceFill(['media_path' => $path, 'media_mime' => $mime])->save();
    }
}

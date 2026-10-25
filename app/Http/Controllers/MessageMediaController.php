<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored chat photo, voice note or document to members of the message's workspace who can see the inbox.
 */
class MessageMediaController
{
    public function __invoke(Request $request, int $message): StreamedResponse
    {
        $model = Message::query()->withoutGlobalScope('workspace')->findOrFail($message);
        $user = $request->user();

        abort_unless($user instanceof User && $user->belongsToTeam(Team::query()->findOrFail($model->team_id)), 404);

        setPermissionsTeamId($model->team_id);
        $user->forgetCachedPermissions();
        abort_unless($user->can('inbox.view'), 403);
        abort_if($model->media_path === null || ! Storage::disk('local')->exists($model->media_path), 404);

        return Storage::disk('local')->response($model->media_path, null, [
            'Content-Type' => $model->media_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}

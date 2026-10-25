<?php

use App\Http\Controllers\MetaWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Meta webhooks: no session, no CSRF (requests are signed with the app secret instead).
 */
Route::get('/webhooks/meta/{channel}', [MetaWebhookController::class, 'verify'])
    ->whereIn('channel', ['whatsapp', 'facebook', 'instagram'])
    ->name('webhooks.meta.verify');

Route::post('/webhooks/meta/{channel}', [MetaWebhookController::class, 'receive'])
    ->whereIn('channel', ['whatsapp', 'facebook', 'instagram'])
    ->name('webhooks.meta.receive');

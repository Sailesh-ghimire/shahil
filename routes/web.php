<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\MessageMediaController;
use App\Http\Controllers\TaskReminderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::get('/invitations/{token}', AcceptInvitationController::class)
    ->middleware('throttle:20,1')
    ->name('invitations.accept');

Route::get('/media/messages/{message}', MessageMediaController::class)
    ->middleware(['auth', 'verified:filament.app.auth.email-verification.prompt'])
    ->name('messages.media');

Route::middleware(['auth', 'verified:filament.app.auth.email-verification.prompt', 'signed'])->group(function (): void {
    Route::get('/tasks/{task}/complete', [TaskReminderController::class, 'complete'])->name('tasks.complete');
    Route::get('/tasks/{task}/snooze', [TaskReminderController::class, 'snooze'])->name('tasks.snooze');
});

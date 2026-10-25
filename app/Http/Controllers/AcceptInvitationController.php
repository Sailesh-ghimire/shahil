<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AcceptInvitation;
use App\Models\TeamInvitation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AcceptInvitationController
{
    public function __invoke(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = TeamInvitation::findByToken($token) ?? abort(404);
        $panel = Filament::getPanel('app');
        $user = $request->user();

        if (! $user instanceof User) {
            $request->session()->put('url.intended', $request->fullUrl());

            $hasAccount = User::query()->where('email', $invitation->email)->exists();

            return redirect($hasAccount ? $panel->getLoginUrl() : $panel->getRegistrationUrl());
        }

        try {
            $acceptInvitation->handle($invitation, $user);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('Invitation not accepted')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return redirect($panel->getUrl());
        }

        Notification::make()
            ->title('Invitation accepted')
            ->body('You are now a member of '.e($invitation->team->name).'.')
            ->success()
            ->send();

        return redirect($panel->getUrl($invitation->team));
    }
}

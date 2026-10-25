<?php

namespace App\Filament\Resources\Members\Actions;

use App\Actions\Workspaces\InviteMember;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Membership;
use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class InviteMemberAction
{
    public static function make(): Action
    {
        return Action::make('invite')
            ->label('Invite member')
            ->icon(Heroicon::OutlinedEnvelope)
            ->authorize('create', Membership::class)
            ->schema([
                TextInput::make('email')
                    ->email()
                    ->required(),
                Select::make('role_id')
                    ->label('Role')
                    ->options(fn (): array => MemberResource::roleOptions())
                    ->default(fn (): ?int => Role::forTeam(currentTeam())->where('name', PermissionCatalog::SALES_REP)->value('id'))
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $team = currentTeam();

                try {
                    app(InviteMember::class)->handle($team, auth()->user(), $data['email'], Role::forTeam($team)->findOrFail($data['role_id']));
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Invitation not sent')->body($exception->getMessage())->warning()->send();
                    $action->halt();
                }

                Notification::make()
                    ->title('Invitation sent')
                    ->body('We emailed '.e($data['email']).' a link to join.')
                    ->success()
                    ->send();
            });
    }
}

<?php

namespace App\Filament\Resources\Invitations\Tables;

use App\Actions\Workspaces\InviteMember;
use App\Models\TeamInvitation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('accepted_at')->with(['role', 'inviter']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No pending invitations')
            ->columns([
                TextColumn::make('email')->searchable()->weight('semibold'),
                TextColumn::make('role.name')->badge()->color('gray'),
                TextColumn::make('inviter.name')->label('Invited by'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->since()
                    ->color(fn (TeamInvitation $record): string => $record->expires_at->isPast() ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('resend')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize('create', TeamInvitation::class)
                    ->action(function (TeamInvitation $record): void {
                        app(InviteMember::class)->handle(currentTeam(), auth()->user(), $record->email, $record->role);

                        Notification::make()->title('Invitation resent')->success()->send();
                    }),
                DeleteAction::make()
                    ->label('Revoke')
                    ->successNotificationTitle('Invitation revoked'),
            ]);
    }
}

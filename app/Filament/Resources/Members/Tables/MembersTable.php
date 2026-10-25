<?php

namespace App\Filament\Resources\Members\Tables;

use App\Actions\Workspaces\ChangeMemberRole;
use App\Actions\Workspaces\RemoveMember;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Membership;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'team']))
            ->defaultSort('joined_at')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->weight('semibold')
                    ->description(fn (Membership $record): string => $record->user->email)
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->state(fn (Membership $record): ?string => $record->role()?->name)
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'Owner' ? 'primary' : 'gray'),
                TextColumn::make('joined_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
                TextColumn::make('last_active_at')
                    ->label('Last active')
                    ->since()
                    ->placeholder('Never'),
            ])
            ->recordActions([
                static::changeRoleAction(),
                static::removeAction(),
            ]);
    }

    public static function changeRoleAction(): Action
    {
        return Action::make('changeRole')
            ->label('Change role')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->authorize('update')
            ->fillForm(fn (Membership $record): array => ['role_id' => $record->role()?->getKey()])
            ->schema([
                Select::make('role_id')
                    ->label('Role')
                    ->options(fn (): array => MemberResource::roleOptions())
                    ->required(),
            ])
            ->action(function (Membership $record, array $data, Action $action): void {
                try {
                    app(ChangeMemberRole::class)->handle($record, Role::forTeam($record->team_id)->findOrFail($data['role_id']));
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Role not changed')->body($exception->getMessage())->warning()->send();
                    $action->halt();
                }

                Notification::make()
                    ->title('Role changed')
                    ->body(e($record->user->name).' is now '.e($record->role()?->name).'.')
                    ->success()
                    ->send();
            });
    }

    public static function removeAction(): Action
    {
        return Action::make('remove')
            ->label('Remove')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->authorize('delete')
            ->requiresConfirmation()
            ->modalDescription(fn (Membership $record): string => "{$record->user->name} will lose access to this workspace. Their records stay.")
            ->action(function (Membership $record, Action $action): void {
                try {
                    app(RemoveMember::class)->handle($record);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Member not removed')->body($exception->getMessage())->warning()->send();
                    $action->halt();
                }

                Notification::make()->title('Member removed')->success()->send();
            });
    }
}

<?php

namespace App\Filament\Tables;

use App\Actions\Messaging\ConnectChannelAccount;
use App\Actions\Messaging\SyncWhatsAppTemplates;
use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Integrations\Meta\ChannelDrivers;
use App\Integrations\Meta\MetaApiException;
use App\Models\ChannelAccount;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ChannelAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ChannelAccount::query()->withCount(['conversations', 'templates']))
            ->defaultSort('channel')
            ->emptyStateHeading('No channels connected')
            ->emptyStateDescription('Connect a WhatsApp number, Facebook Page or Instagram account to start receiving messages.')
            ->columns([
                TextColumn::make('channel')->badge(),
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (ChannelAccount $record): string => $record->display_handle ?? $record->external_id),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (ChannelAccount $record): ?string => $record->status_message),
                TextColumn::make('conversations_count')->label('Chats'),
                TextColumn::make('templates_count')
                    ->label('Templates')
                    ->state(fn (ChannelAccount $record): string => $record->channel === Channel::WhatsApp ? (string) $record->getAttribute('templates_count') : '—'),
                TextColumn::make('token_expires_at')
                    ->label('Token expires')
                    ->date()
                    ->placeholder('Never'),
                TextColumn::make('last_checked_at')
                    ->label('Last checked')
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('check')
                        ->label('Check connection')
                        ->icon(Heroicon::OutlinedSignal)
                        ->action(function (ChannelAccount $record): void {
                            $status = app(ChannelDrivers::class)->for($record->channel)->checkToken($record);

                            $record->forceFill([
                                'last_checked_at' => now(),
                                'token_expires_at' => $status->expiresAt,
                                'status' => $status->isValid ? ChannelAccountStatus::Connected : ChannelAccountStatus::ReconnectNeeded,
                                'status_message' => $status->message,
                            ])->save();

                            $status->isValid
                                ? Notification::make()->title('Connection works')->success()->send()
                                : Notification::make()->title('Reconnect needed')->body(e((string) $status->message))->danger()->persistent()->send();
                        }),
                    Action::make('syncTemplates')
                        ->label('Sync templates')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (ChannelAccount $record): bool => $record->channel === Channel::WhatsApp && $record->isUsable())
                        ->action(function (ChannelAccount $record): void {
                            try {
                                $count = app(SyncWhatsAppTemplates::class)->handle($record);
                            } catch (MetaApiException|InvalidArgumentException $exception) {
                                Notification::make()->title('Templates not synced')->body(e($exception->getMessage()))->danger()->send();

                                return;
                            }

                            Notification::make()->title('Templates synced')->body($count.' approved '.str('template')->plural($count).'.')->success()->send();
                        }),
                    Action::make('reconnect')
                        ->label('Reconnect')
                        ->icon(Heroicon::OutlinedKey)
                        ->modalDescription('Paste a new access token for this account.')
                        ->schema([
                            Textarea::make('access_token')->label('New access token')->required()->rows(3),
                        ])
                        ->action(function (ChannelAccount $record, array $data, Action $action): void {
                            try {
                                app(ConnectChannelAccount::class)->handle(currentTeam(), auth()->user(), $record->channel, [
                                    'name' => $record->name,
                                    'external_id' => $record->external_id,
                                    'display_handle' => $record->display_handle,
                                    'business_account_id' => $record->business_account_id,
                                    'access_token' => $data['access_token'],
                                ]);
                            } catch (InvalidArgumentException $exception) {
                                Notification::make()->title('Not reconnected')->body(e($exception->getMessage()))->danger()->send();
                                $action->halt();

                                return;
                            }

                            Notification::make()->title('Reconnected')->body(e($record->name).' is working again.')->success()->send();
                        }),
                    Action::make('disconnect')
                        ->label('Disconnect')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('New messages to this account will no longer reach the CRM. Existing chats stay.')
                        ->visible(fn (ChannelAccount $record): bool => $record->status !== ChannelAccountStatus::Disconnected)
                        ->action(function (ChannelAccount $record): void {
                            app(ConnectChannelAccount::class)->disconnect($record);

                            Notification::make()->title('Disconnected')->body(e($record->name))->success()->send();
                        }),
                ]),
            ]);
    }
}

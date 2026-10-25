<?php

namespace App\Filament\Resources\Deals\Actions;

use App\Actions\Sales\MoveDealToStage;
use App\Models\Deal;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * "Mark won" (asks for the close date) and "Mark lost" (asks for a reason) on the deal page.
 */
class CloseDealActions
{
    public static function markWon(): Action
    {
        return Action::make('markWon')
            ->label('Mark won')
            ->icon(Heroicon::OutlinedTrophy)
            ->color('success')
            ->authorize('update')
            ->visible(fn (Deal $record): bool => $record->closed_at === null && $record->pipeline->wonStage() !== null)
            ->schema([
                DatePicker::make('closed_at')
                    ->label('Close date')
                    ->default(now())
                    ->maxDate(now()->addDay())
                    ->required(),
            ])
            ->action(function (Deal $record, array $data): void {
                app(MoveDealToStage::class)->handle($record, $record->pipeline->wonStage(), Carbon::parse($data['closed_at']));

                Notification::make()->title('Deal won')->body(e($record->title).' is counted in revenue.')->success()->send();
            });
    }

    public static function markLost(): Action
    {
        return Action::make('markLost')
            ->label('Mark lost')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Deal $record): bool => $record->closed_at === null && $record->pipeline->lostStage() !== null)
            ->schema([
                TextInput::make('lost_reason')
                    ->label('Why was it lost?')
                    ->datalist(['Price too high', 'Chose a competitor', 'No budget', 'No decision', 'Timing'])
                    ->required(),
            ])
            ->action(function (Deal $record, array $data): void {
                app(MoveDealToStage::class)->handle($record, $record->pipeline->lostStage(), lostReason: $data['lost_reason']);

                Notification::make()->title('Deal marked lost')->body(e($record->title).' moved to Lost.')->warning()->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Deal $record): bool => $record->closed_at !== null)
            ->requiresConfirmation()
            ->action(function (Deal $record): void {
                app(MoveDealToStage::class)->handle($record, $record->pipeline->firstOpenStage());

                Notification::make()->title('Deal reopened')->success()->send();
            });
    }
}

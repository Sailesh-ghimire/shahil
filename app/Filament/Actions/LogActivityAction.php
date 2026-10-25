<?php

namespace App\Filament\Actions;

use App\Enums\ActivityType;
use App\Models\Contact;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * "Log activity" on any record with a timeline: a call, meeting, note or email.
 */
class LogActivityAction
{
    public static function make(string $name = 'logActivity'): Action
    {
        return Action::make($name)
            ->label('Log activity')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (Model $record): bool => static::canLog($record))
            ->schema([
                Select::make('type')
                    ->options(collect(ActivityType::loggable())->mapWithKeys(fn (ActivityType $type): array => [$type->value => $type->getLabel()])->all())
                    ->default(ActivityType::Call->value)
                    ->required(),
                TextInput::make('title')
                    ->placeholder('Intro call')
                    ->maxLength(255),
                Textarea::make('body')
                    ->label('Notes')
                    ->rows(4),
                TextInput::make('outcome')
                    ->placeholder('Interested, send proposal')
                    ->maxLength(255),
                DateTimePicker::make('happened_at')
                    ->label('When')
                    ->default(now())
                    ->seconds(false)
                    ->required(),
            ])
            ->action(function (Model $record, array $data): void {
                /** @var Contact $record */
                $record->logActivity(
                    type: ActivityType::from($data['type']),
                    body: $data['body'] ?? null,
                    happenedAt: Carbon::parse($data['happened_at']),
                    title: $data['title'] ?? null,
                    outcome: $data['outcome'] ?? null,
                );

                Notification::make()
                    ->title('Activity logged')
                    ->success()
                    ->send();
            });
    }

    public static function canLog(Model $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! method_exists($record, 'logActivity')) {
            return false;
        }

        return $user->can('update', $record) || $user->can('view', $record) && static::canCreateIn($user, $record);
    }

    /**
     * Anyone who can add records to a module can log calls and notes on its records.
     */
    protected static function canCreateIn(User $user, Model $record): bool
    {
        return $user->can('create', $record::class);
    }
}

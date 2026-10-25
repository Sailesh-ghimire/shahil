<?php

namespace App\Filament\Actions;

use App\Enums\ReminderOffset;
use App\Enums\TaskType;
use App\Filament\Forms\OwnerSelect;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * "Add follow-up" on any contact, company, lead or deal.
 */
class AddFollowUpAction
{
    public static function make(string $name = 'addFollowUp'): Action
    {
        return Action::make($name)
            ->label('Add follow-up')
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('create', Task::class) ?? false)
            ->schema(static::schema())
            ->action(function (Model $record, array $data): void {
                static::create($record, $data);
            });
    }

    /**
     * @return array<Component|Field>
     */
    public static function schema(?string $defaultTitle = null): array
    {
        return [
            TextInput::make('title')
                ->default($defaultTitle)
                ->placeholder('Call Sita about the proposal')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->options(TaskType::class)
                ->default(TaskType::Call->value)
                ->required(),
            DateTimePicker::make('due_at')
                ->label('Due')
                ->seconds(false)
                ->default(now()->addDay()->setTime(10, 0))
                ->required(),
            Select::make('remind_minutes_before')
                ->label('Reminder')
                ->options(ReminderOffset::class)
                ->default(ReminderOffset::FifteenMinutes->value)
                ->placeholder('No reminder'),
            OwnerSelect::make('assignee_id', 'Assignee'),
            Textarea::make('notes')->rows(2),
        ];
    }

    /**
     * The reminder select may hand back the enum or its raw value.
     */
    protected static function minutes(mixed $value): ?int
    {
        return match (true) {
            $value instanceof ReminderOffset => $value->value,
            filled($value) => (int) $value,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(?Model $subject, array $data): Task
    {
        $task = Task::query()->create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'title' => $data['title'],
            'type' => $data['type'],
            'due_at' => Carbon::parse($data['due_at']),
            'remind_minutes_before' => static::minutes($data['remind_minutes_before'] ?? null),
            'assignee_id' => $data['assignee_id'] ?? auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        Notification::make()
            ->title('Follow-up scheduled')
            ->body(e($task->title).' — '.$task->due_at->format('D j M, H:i').'.')
            ->success()
            ->actions([
                Action::make('viewTasks')
                    ->label('View tasks')
                    ->link()
                    ->url(TaskResource::getUrl()),
            ])
            ->send();

        return $task;
    }
}

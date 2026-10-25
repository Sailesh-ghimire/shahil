<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\ReminderOffset;
use App\Enums\TaskType;
use App\Filament\Forms\OwnerSelect;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(TaskType::class)
                    ->default(TaskType::Call->value)
                    ->required(),
                OwnerSelect::make('assignee_id', 'Assignee'),
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
                MorphToSelect::make('subject')
                    ->label('Related to')
                    ->types([
                        MorphToSelect\Type::make(Contact::class)->titleAttribute('first_name')
                            ->getOptionLabelFromRecordUsing(fn (Contact $record): string => $record->name),
                        MorphToSelect\Type::make(Company::class)->titleAttribute('name'),
                        MorphToSelect\Type::make(Lead::class)->titleAttribute('name'),
                        MorphToSelect\Type::make(Deal::class)->titleAttribute('title'),
                    ])
                    ->searchable()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}

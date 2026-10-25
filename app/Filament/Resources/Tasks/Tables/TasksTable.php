<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Filament\Forms\OwnerSelect;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Deals\DealResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['assignee', 'subject']))
            ->defaultSort('due_at')
            ->emptyStateHeading('Nothing to do here')
            ->columns([
                IconColumn::make('type')
                    ->label('')
                    ->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->description(fn (Task $record): ?string => static::subjectLabel($record))
                    ->url(fn (Task $record): ?string => static::subjectUrl($record))
                    ->searchable(),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime('D j M, H:i')
                    ->description(fn (Task $record): ?string => $record->isCompleted() ? null : $record->due_at->diffForHumans())
                    ->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('remind_minutes_before')
                    ->label('Reminder')
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        null => '—',
                        0 => 'At due time',
                        15 => '15 min before',
                        60 => '1 hour before',
                        1440 => '1 day before',
                        default => $state.' min before',
                    })
                    ->toggleable(),
                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('—'),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->since()
                    ->placeholder('Open')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('assignee_id')
                    ->label('Assignee')
                    ->options(fn (): array => OwnerSelect::options()),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Done')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (Task $record): bool => ! $record->isCompleted())
                    ->action(function (Task $record): void {
                        $record->complete();

                        Notification::make()
                            ->title('Task completed')
                            ->body(e($record->title))
                            ->success()
                            ->actions([
                                Action::make('undo')->label('Undo')->color('gray')->dispatch('reopenTask', [$record->getKey()])->close(),
                            ])
                            ->send();
                    }),
                ActionGroup::make([
                    Action::make('snoozeHour')
                        ->label('Snooze 1 hour')
                        ->icon(Heroicon::OutlinedClock)
                        ->authorize('update')
                        ->visible(fn (Task $record): bool => ! $record->isCompleted())
                        ->action(function (Task $record): void {
                            $record->snooze(60);
                            Notification::make()->title('Snoozed for 1 hour')->info()->send();
                        }),
                    Action::make('tomorrow')
                        ->label('Move to tomorrow')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->authorize('update')
                        ->visible(fn (Task $record): bool => ! $record->isCompleted())
                        ->action(function (Task $record): void {
                            $record->update(['due_at' => $record->due_at->copy()->addDay()]);
                            Notification::make()->title('Moved to tomorrow')->info()->send();
                        }),
                    Action::make('reopen')
                        ->label('Reopen')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->authorize('update')
                        ->visible(fn (Task $record): bool => $record->isCompleted())
                        ->action(fn (Task $record) => $record->reopen()),
                    EditAction::make()->label('Reschedule or edit'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('completeSelected')
                        ->label('Mark done')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (mixed $task): bool => $task instanceof Task && auth()->user()->can('update', $task))
                                ->each(fn (Task $task) => $task->complete());
                            Notification::make()->title('Tasks completed')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function subjectLabel(Task $task): ?string
    {
        $subject = $task->subject;

        return match (true) {
            $subject instanceof Contact => 'Contact: '.$subject->name,
            $subject instanceof Company => 'Company: '.$subject->name,
            $subject instanceof Lead => 'Lead: '.$subject->name,
            $subject instanceof Deal => 'Deal: '.$subject->title,
            default => null,
        };
    }

    public static function subjectUrl(Task $task): ?string
    {
        $subject = $task->subject;

        return match (true) {
            $subject instanceof Contact => ContactResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Company => CompanyResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Lead => LeadResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Deal => DealResource::getUrl('view', ['record' => $subject]),
            default => null,
        };
    }
}

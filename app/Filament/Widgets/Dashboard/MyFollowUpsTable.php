<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * My open follow-ups due today or overdue.
 */
class MyFollowUpsTable extends TableWidget
{
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $endOfToday = now(auth()->user()->timezone)->endOfDay()->utc();

        return $table
            ->heading('My follow-ups')
            ->description('Due today and overdue')
            ->query(fn (): Builder => Task::query()
                ->with('subject')
                ->where('assignee_id', auth()->id())
                ->whereNull('completed_at')
                ->where('due_at', '<=', $endOfToday))
            ->defaultSort('due_at')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('All caught up')
            ->columns([
                IconColumn::make('type')->label('')->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->description(fn (Task $record): ?string => TasksTable::subjectLabel($record))
                    ->url(fn (Task $record): ?string => TasksTable::subjectUrl($record)),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime('H:i')
                    ->description(fn (Task $record): string => $record->due_at->isToday() ? 'Today' : $record->due_at->diffForHumans())
                    ->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Done')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->action(function (Task $record): void {
                        $this->authorize('update', $record);
                        $record->complete();
                        Notification::make()->title('Task completed')->body(e($record->title))->success()->send();
                    }),
            ]);
    }
}

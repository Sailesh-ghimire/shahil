<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New task')
                ->successNotificationTitle('Follow-up scheduled'),
        ];
    }

    /**
     * Today, Overdue, Upcoming and Completed — in the user's own timezone.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tz = auth()->user()->timezone;
        $startOfToday = now($tz)->startOfDay()->utc();
        $endOfToday = now($tz)->endOfDay()->utc();

        return [
            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('completed_at')->whereBetween('due_at', [$startOfToday, $endOfToday]))
                ->badge(fn (): int => TaskResource::getEloquentQuery()->whereNull('completed_at')->whereBetween('due_at', [$startOfToday, $endOfToday])->count()),
            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('completed_at')->where('due_at', '<', $startOfToday))
                ->badge(fn (): int => TaskResource::getEloquentQuery()->whereNull('completed_at')->where('due_at', '<', $startOfToday)->count())
                ->badgeColor('danger'),
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('completed_at')->where('due_at', '>', $endOfToday)),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('completed_at')->reorder('completed_at', 'desc')),
        ];
    }

    #[On('reopenTask')]
    public function reopenTask(int $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);
        $this->authorize('update', $task);
        $task->reopen();
    }
}

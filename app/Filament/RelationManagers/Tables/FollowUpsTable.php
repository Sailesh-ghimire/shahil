<?php

namespace App\Filament\RelationManagers\Tables;

use App\Filament\Actions\AddFollowUpAction;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowUpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('assignee')->orderByRaw('completed_at is not null')->orderBy('due_at'))
            ->emptyStateHeading('No follow-ups')
            ->columns([
                IconColumn::make('type')->label(''),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->color(fn (Task $record): ?string => $record->isCompleted() ? 'gray' : null),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->dateTime('D j M, H:i')
                    ->color(fn (Task $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('assignee.name')->label('Assignee')->placeholder('—'),
                TextColumn::make('completed_at')->label('Done')->since()->placeholder('Open'),
            ])
            ->headerActions([
                Action::make('addFollowUp')
                    ->label('Add follow-up')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->visible(fn (): bool => auth()->user()?->can('create', Task::class) ?? false)
                    ->schema(AddFollowUpAction::schema())
                    ->action(fn (array $data, RelationManager $livewire) => AddFollowUpAction::create($livewire->getOwnerRecord(), $data)),
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
                        Notification::make()->title('Task completed')->body(e($record->title))->success()->send();
                    }),
            ]);
    }
}

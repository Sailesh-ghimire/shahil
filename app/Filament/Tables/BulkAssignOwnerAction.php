<?php

namespace App\Filament\Tables;

use App\Filament\Forms\OwnerSelect;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Reassigns the selected records to another member. Only records the user may edit are changed.
 */
class BulkAssignOwnerAction
{
    public static function make(string $ownerColumn = 'owner_id'): BulkAction
    {
        return BulkAction::make('assignOwner')
            ->label('Assign owner')
            ->icon(Heroicon::OutlinedUserCircle)
            ->schema([
                OwnerSelect::make($ownerColumn, 'New owner')->required(),
            ])
            ->action(function (Collection $records, array $data) use ($ownerColumn): void {
                $changed = $records
                    ->filter(fn (Model $record): bool => auth()->user()->can('update', $record))
                    ->each(fn (Model $record) => $record->update([$ownerColumn => $data[$ownerColumn]]))
                    ->count();

                Notification::make()
                    ->title('Owner assigned')
                    ->body($changed.' of '.$records->count().' records reassigned.')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}

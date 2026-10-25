<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Actions\Workspaces\SaveRole;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['permissions', 'users']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Role $record): ?string => $record->description)
                    ->searchable(),
                IconColumn::make('is_system')
                    ->label('Built in')
                    ->boolean(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('Members'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (Role $record, DeleteAction $action): bool {
                        try {
                            app(SaveRole::class)->delete($record);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()
                                ->title('Role not deleted')
                                ->body($exception->getMessage())
                                ->warning()
                                ->send();

                            $action->halt();
                        }

                        return true;
                    }),
            ]);
    }
}

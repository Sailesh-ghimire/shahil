<?php

namespace App\Filament\Resources\Companies\RelationManagers\Tables;

use App\Filament\Resources\Contacts\ContactResource;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyContactsTable
{
    public static function configure(Table $table): Table
    {
        $canEditCompany = fn (RelationManager $livewire): bool => auth()->user()?->can('update', $livewire->getOwnerRecord()) ?? false;

        return $table
            ->recordTitleAttribute('first_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('methods'))
            ->emptyStateHeading('No people yet')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (Contact $record): ?string => $record->primaryEmail())
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('job_title')
                    ->label('Job title')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->state(fn (Contact $record): ?string => $record->primaryPhone())
                    ->placeholder('—'),
                IconColumn::make('is_primary')
                    ->label('Main contact')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->authorize($canEditCompany)
                    ->label('Add person')
                    ->recordSelectSearchColumns(['first_name', 'last_name'])
                    ->recordTitle(fn (Contact $record): string => $record->name)
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('job_title')->label('Job title')->maxLength(255),
                        Toggle::make('is_primary')->label('Main contact'),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'team_id' => currentTeam()->getKey()]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Contact $record): string => ContactResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->authorize($canEditCompany)
                    ->label('Edit title')
                    ->schema([
                        TextInput::make('job_title')->label('Job title')->maxLength(255),
                        Toggle::make('is_primary')->label('Main contact'),
                    ]),
                DetachAction::make()->label('Remove')->authorize($canEditCompany),
            ]);
    }
}

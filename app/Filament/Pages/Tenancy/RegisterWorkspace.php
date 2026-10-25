<?php

namespace App\Filament\Pages\Tenancy;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Team;
use App\Support\Workspace\WorkspaceOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterWorkspace extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create workspace';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Workspace name')
                    ->placeholder('Acme Traders')
                    ->required()
                    ->maxLength(120),
                Select::make('currency')
                    ->options(WorkspaceOptions::currencies())
                    ->default('NPR')
                    ->required(),
                Select::make('timezone')
                    ->options(WorkspaceOptions::timezones())
                    ->default('Asia/Kathmandu')
                    ->searchable()
                    ->required(),
            ]);
    }

    /**
     * @param  array{name: string, currency: string, timezone: string}  $data
     */
    protected function handleRegistration(array $data): Team
    {
        return app(CreateWorkspace::class)->handle(auth()->user(), $data);
    }
}

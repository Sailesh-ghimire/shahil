<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\MoneyInput;
use App\Support\Workspace\WorkspaceOptions;
use App\Support\Workspace\WorkspaceSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditWorkspaceProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Workspace settings';
    }

    public static function canView(Model $tenant): bool
    {
        return auth()->user()?->can('workspace.manage') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Workspace name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('slug')
                    ->label('URL name')
                    ->helperText('Used in links, for example /app/acme-traders.')
                    ->required()
                    ->alphaDash()
                    ->maxLength(80)
                    ->unique(ignoreRecord: true),
                Select::make('currency')
                    ->options(WorkspaceOptions::currencies())
                    ->required(),
                Select::make('timezone')
                    ->options(WorkspaceOptions::timezones())
                    ->searchable()
                    ->required(),
                Section::make('Sales rules')
                    ->columns(2)
                    ->schema([
                        TextInput::make('settings.stale_deal_days')
                            ->label('Follow up on deals idle for')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->suffix('days')
                            ->default(WorkspaceSettings::DEFAULT_STALE_DEAL_DAYS)
                            ->helperText('Creates a follow-up for the owner. 0 turns it off.'),
                        MoneyInput::make('settings.monthly_revenue_target')
                            ->label('Monthly revenue target')
                            ->helperText('Shown on the Reports page.'),
                    ]),
            ]);
    }
}

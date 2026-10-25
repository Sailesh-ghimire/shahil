<?php

namespace App\Filament\Pages\Auth;

use App\Support\Workspace\WorkspaceOptions;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Profile with the user's timezone: reminders and due times are shown in it.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Select::make('timezone')
                    ->options(WorkspaceOptions::timezones())
                    ->searchable()
                    ->required()
                    ->helperText('Due dates and reminders are shown in this timezone.'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}

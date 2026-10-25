<?php

namespace App\Filament\Resources\Invitations;

use App\Filament\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Resources\Invitations\Tables\InvitationsTable;
use App\Models\TeamInvitation;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvitationResource extends Resource
{
    protected static ?string $model = TeamInvitation::class;

    protected static ?string $slug = 'invitations';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Scoped by hand below: Filament's automatic tenancy would re-assign records created
     * for another workspace.
     */
    protected static bool $isScopedToTenant = false;

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return InvitationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', Filament::getTenant()?->getKey());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}

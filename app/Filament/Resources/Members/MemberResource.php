<?php

namespace App\Filament\Resources\Members;

use App\Actions\Workspaces\OwnerGuard;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Membership;
use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MemberResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $modelLabel = 'member';

    protected static ?string $slug = 'members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    /**
     * Scoped by hand below: Filament's automatic tenancy would re-assign records created
     * for another workspace (for example memberships made while creating a new workspace).
     */
    protected static bool $isScopedToTenant = false;

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', Filament::getTenant()?->getKey());
    }

    /**
     * Roles of the current workspace, for role pickers.
     *
     * @return array<int, string>
     */
    public static function roleOptions(): array
    {
        $team = currentTeam();

        return Role::forTeam($team)
            ->when(! app(OwnerGuard::class)->isOwner(auth()->user(), $team->getKey()), fn ($query) => $query->where('name', '!=', PermissionCatalog::OWNER))
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
        ];
    }
}

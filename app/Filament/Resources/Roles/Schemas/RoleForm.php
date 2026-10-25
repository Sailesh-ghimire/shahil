<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(60)
                            ->unique(
                                table: 'roles',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('team_id', Filament::getTenant()?->getKey())
                                    ->where('guard_name', 'web'),
                            ),
                        TextInput::make('description')
                            ->maxLength(255),
                    ]),
                Section::make('Permissions')
                    ->description('Grouped by module. Changes apply on each member’s next request.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema(static::permissionGroups()),
                    ]),
            ]);
    }

    /**
     * @return list<CheckboxList>
     */
    public static function permissionGroups(): array
    {
        $fields = [];

        foreach (PermissionCatalog::groups() as $group => $permissions) {
            $fields[] = CheckboxList::make(static::groupField($group))
                ->label($group)
                ->options(collect($permissions)->mapWithKeys(fn (string $permission): array => [$permission => PermissionCatalog::label($permission)])->all())
                ->descriptions(collect($permissions)->mapWithKeys(fn (string $permission): array => [$permission => $permission])->all())
                ->bulkToggleable();
        }

        return $fields;
    }

    public static function groupField(string $group): string
    {
        return 'permissions_'.Str::slug($group, '_');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function fillPermissions(Role $role): array
    {
        $granted = $role->permissions->pluck('name')->all();
        $data = [];

        foreach (PermissionCatalog::groups() as $group => $permissions) {
            $data[static::groupField($group)] = array_values(array_intersect($permissions, $granted));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function collectPermissions(array $data): array
    {
        $permissions = [];

        foreach (array_keys(PermissionCatalog::groups()) as $group) {
            $permissions = [...$permissions, ...($data[static::groupField($group)] ?? [])];
        }

        return array_values(array_unique($permissions));
    }
}

<?php

use App\Actions\Workspaces\AssignWorkspaceRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Spatie\Permission\Models\Permission;

it('creates a workspace with five default roles and makes the creator its owner', function () {
    $owner = User::factory()->create();

    $team = $this->createWorkspace($owner, 'Acme Traders');

    expect($team->slug)->toBe('acme-traders')
        ->and($team->currency)->toBe('NPR')
        ->and($team->members()->whereKey($owner)->exists())->toBeTrue()
        ->and(Role::query()->where('team_id', $team->id)->orderBy('id')->pluck('name')->all())
        ->toBe([PermissionCatalog::OWNER, PermissionCatalog::ADMIN, PermissionCatalog::MANAGER, PermissionCatalog::SALES_REP, PermissionCatalog::VIEWER]);

    $this->actingInWorkspace($owner, $team);

    expect($owner->hasRole(PermissionCatalog::OWNER))->toBeTrue();
});

it('defines exactly 34 global permissions', function () {
    $this->createWorkspace();

    expect(PermissionCatalog::all())->toHaveCount(34)
        ->and(Permission::query()->count())->toBe(34);
});

it('keeps slugs unique', function () {
    $first = $this->createWorkspace(name: 'Acme Traders');
    $second = $this->createWorkspace(name: 'Acme Traders');

    expect($first->slug)->toBe('acme-traders')
        ->and($second->slug)->toBe('acme-traders-2');
});

it('keeps roles separate per workspace', function () {
    $acme = $this->createWorkspace(name: 'Acme Traders');
    $himalaya = $this->createWorkspace(name: 'Himalaya Exports');

    $acmeRep = Role::query()->where('team_id', $acme->id)->where('name', PermissionCatalog::SALES_REP)->first();
    $acmeRep->givePermissionTo('contacts.delete');

    $himalayaRep = Role::query()->where('team_id', $himalaya->id)->where('name', PermissionCatalog::SALES_REP)->first();

    expect($acmeRep->fresh()->hasPermissionTo('contacts.delete'))->toBeTrue()
        ->and($himalayaRep->fresh()->hasPermissionTo('contacts.delete'))->toBeFalse();
});

it('gives the same person different roles in different workspaces', function () {
    $person = User::factory()->create();
    $acme = $this->createWorkspace($person, 'Acme Traders');
    $himalaya = $this->createWorkspace(name: 'Himalaya Exports');
    $himalaya->members()->attach($person);
    app(AssignWorkspaceRole::class)->handle($person, $himalaya, PermissionCatalog::SALES_REP);

    $this->actingInWorkspace($person, $acme);
    expect($person->can('workspace.manage'))->toBeTrue();

    $this->actingInWorkspace($person, $himalaya);
    expect($person->hasRole(PermissionCatalog::SALES_REP))->toBeTrue()
        ->and($person->can('workspace.manage'))->toBeFalse()
        ->and($person->can('contacts.update.own'))->toBeTrue();
});

it('lists only the user’s own workspaces as tenants', function () {
    $user = User::factory()->create();
    $mine = $this->createWorkspace($user, 'Mine');
    $other = $this->createWorkspace(name: 'Other');

    expect($user->canAccessTenant($mine))->toBeTrue()
        ->and($user->canAccessTenant($other))->toBeFalse()
        ->and(Team::query()->count())->toBe(2);
});

<?php

use App\Actions\Workspaces\AssignWorkspaceRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->acme = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->himalaya = $this->createWorkspace(name: 'Himalaya Exports');
});

it('sends guests to the login page', function () {
    $this->get('/app/acme-traders')->assertRedirect('/app/login');
});

it('opens the member’s own workspace pages', function () {
    $this->actingAs($this->owner);

    $this->get('/app')->assertRedirect('/app/acme-traders');
    $this->get('/app/acme-traders')->assertOk()->assertSee('Acme Traders');
    $this->get('/app/acme-traders/roles')->assertOk()->assertSee('Sales rep');
    $this->get('/app/acme-traders/members')->assertOk()->assertSee($this->owner->email);
    $this->get('/app/acme-traders/invitations')->assertOk()->assertSee('No pending invitations');
    $this->get('/app/acme-traders/profile')->assertOk()->assertSee('Workspace settings');
    $this->get('/app/acme-traders/roles/create')->assertOk();
});

it('refuses a workspace the user does not belong to', function () {
    $this->actingAs($this->owner)
        ->get('/app/himalaya-exports')
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->get('/app/himalaya-exports/roles')
        ->assertNotFound();
});

it('cannot open another workspace’s role by guessing its id', function () {
    $foreignRole = Role::forTeam($this->himalaya)->where('name', PermissionCatalog::MANAGER)->first();

    $this->actingAs($this->owner)
        ->get("/app/acme-traders/roles/{$foreignRole->id}/edit")
        ->assertNotFound();
});

it('blocks settings pages for roles without the permission', function () {
    $rep = $this->memberOf($this->acme, PermissionCatalog::SALES_REP);

    $this->actingAs($rep)->get('/app/acme-traders/roles')->assertForbidden();
    $this->actingAs($rep)->get('/app/acme-traders/members')->assertForbidden();
    $this->actingAs($rep)->get('/app/acme-traders/profile')->assertNotFound();
});

it('remembers the last workspace used', function () {
    $this->himalaya->members()->attach($this->owner);
    app(AssignWorkspaceRole::class)->handle($this->owner, $this->himalaya, PermissionCatalog::ADMIN);

    $this->actingAs($this->owner)->get('/app/himalaya-exports')->assertOk();

    expect($this->owner->fresh()->last_team_id)->toBe($this->himalaya->id);

    $this->actingAs($this->owner->fresh())->get('/app')->assertRedirect('/app/himalaya-exports');
});

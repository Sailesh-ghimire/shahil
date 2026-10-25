<?php

use App\Actions\Workspaces\AcceptInvitation;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Membership;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\WorkspaceInvitation;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->actingInWorkspace($this->owner, $this->team);
});

it('lists only this workspace’s roles', function () {
    $other = $this->createWorkspace(name: 'Himalaya Exports');
    $this->actingInWorkspace($this->owner, $this->team);

    livewire(ListRoles::class)
        ->assertCanSeeTableRecords(Role::query()->where('team_id', $this->team->id)->get())
        ->assertCanNotSeeTableRecords(Role::query()->where('team_id', $other->id)->get());
});

it('creates a custom role from the permission checklist', function () {
    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'Support agent',
            'description' => 'Works the inbox only',
            'permissions_inbox' => ['inbox.view', 'inbox.reply.assigned'],
            'permissions_contacts_companies' => ['contacts.view'],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Role created');

    $role = Role::query()->where('team_id', $this->team->id)->where('name', 'Support agent')->firstOrFail();

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['contacts.view', 'inbox.reply.assigned', 'inbox.view']);
});

it('rejects a duplicate role name in the same workspace', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => PermissionCatalog::MANAGER])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('does not let anyone edit or delete the Owner role', function () {
    $ownerRole = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::OWNER)->first();

    expect($this->owner->can('update', $ownerRole))->toBeFalse()
        ->and($this->owner->can('delete', $ownerRole))->toBeFalse();

    livewire(EditRole::class, ['record' => $ownerRole->getRouteKey()])->assertForbidden();
});

it('refuses to delete a role that members still hold', function () {
    $rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $repRole = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::SALES_REP)->first();

    livewire(ListRoles::class)
        ->callTableAction('delete', $repRole)
        ->assertNotified('Role not deleted');

    expect(Role::query()->whereKey($repRole->id)->exists())->toBeTrue();
});

it('hides roles and members from a sales rep', function () {
    $rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $this->actingInWorkspace($rep, $this->team);

    livewire(ListRoles::class)->assertForbidden();
    livewire(ListMembers::class)->assertForbidden();
});

it('invites a member by email and joins them with the chosen role', function () {
    Notification::fake();
    $manager = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::MANAGER)->first();

    livewire(ListMembers::class)
        ->callAction('invite', ['email' => 'sanjay@acme.example', 'role_id' => $manager->id])
        ->assertNotified('Invitation sent');

    $invitation = TeamInvitation::query()->where('email', 'sanjay@acme.example')->firstOrFail();
    Notification::assertSentOnDemand(WorkspaceInvitation::class);

    $sanjay = User::factory()->create(['email' => 'sanjay@acme.example']);
    app(AcceptInvitation::class)->handle($invitation, $sanjay);

    $this->actingInWorkspace($sanjay, $this->team);
    expect($sanjay->hasRole(PermissionCatalog::MANAGER))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('accepts an invitation through the emailed link', function () {
    $viewer = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::VIEWER)->first();
    $invitation = TeamInvitation::query()->create([
        'team_id' => $this->team->id,
        'email' => 'nisha@acme.example',
        'role_id' => $viewer->id,
        'token' => TeamInvitation::hashToken(str_repeat('a', 64)),
        'expires_at' => now()->addDay(),
    ]);
    $nisha = User::factory()->create(['email' => 'nisha@acme.example']);

    $this->actingAs($nisha)
        ->get(route('invitations.accept', str_repeat('a', 64)))
        ->assertRedirect('/app/acme-traders');

    expect($nisha->belongsToTeam($this->team))->toBeTrue();
});

it('keeps at least one owner', function () {
    $membership = Membership::query()->where('team_id', $this->team->id)->where('user_id', $this->owner->id)->first();
    $viewer = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::VIEWER)->first();

    livewire(ListMembers::class)
        ->callTableAction('changeRole', $membership, ['role_id' => $viewer->id])
        ->assertNotified('Role not changed');

    livewire(ListMembers::class)
        ->callTableAction('remove', $membership)
        ->assertNotified('Member not removed');

    expect($this->team->members()->whereKey($this->owner)->exists())->toBeTrue();
});

it('changes a member’s role and removes members', function () {
    $rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $membership = Membership::query()->where('team_id', $this->team->id)->where('user_id', $rep->id)->first();
    $manager = Role::query()->where('team_id', $this->team->id)->where('name', PermissionCatalog::MANAGER)->first();

    livewire(ListMembers::class)
        ->callTableAction('changeRole', $membership, ['role_id' => $manager->id])
        ->assertNotified('Role changed');

    expect($membership->fresh()->role()->name)->toBe(PermissionCatalog::MANAGER);

    livewire(ListMembers::class)
        ->callTableAction('remove', $membership)
        ->assertNotified('Member removed');

    expect($rep->belongsToTeam($this->team))->toBeFalse();
});

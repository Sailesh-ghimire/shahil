<?php

use App\Actions\Workspaces\ChangeMemberRole;
use App\Actions\Workspaces\InviteMember;
use App\Filament\Exports\ContactExporter;
use App\Filament\Imports\ContactImporter;
use App\Filament\Resources\Deals\Pages\DealsBoard;
use App\Filament\Resources\Deals\Pages\ViewDeal;
use App\Filament\Resources\Deals\RelationManagers\LineItemsRelationManager;
use App\Filament\Resources\Leads\Pages\LeadsBoard;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Stage;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\WorkspaceInvitation;
use App\Support\Permissions\PermissionCatalog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/**
 * Regression tests for the Phase 5 security review findings.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->admin = $this->memberOf($this->team, PermissionCatalog::ADMIN);
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
});

it('joins an invited workspace only after the email is verified (H1)', function () {
    Notification::fake();
    $role = Role::forTeam($this->team)->where('name', PermissionCatalog::SALES_REP)->first();
    app(InviteMember::class)->handle($this->team, $this->owner, 'newhire@acme.example', $role);

    $stranger = User::factory()->unverified()->create(['email' => 'newhire@acme.example']);

    expect($stranger->belongsToTeam($this->team))->toBeFalse();

    $stranger->markEmailAsVerified();
    event(new Verified($stranger));

    expect($stranger->belongsToTeam($this->team))->toBeTrue();
});

it('blocks unverified accounts from chat media and reminder links (H1)', function () {
    $unverified = User::factory()->unverified()->create();
    $this->team->members()->attach($unverified);

    $this->actingAs($unverified)->get('/media/messages/1')->assertRedirect();
});

it('stores only a hash of invitation tokens (L3)', function () {
    Notification::fake();
    $role = Role::forTeam($this->team)->where('name', PermissionCatalog::VIEWER)->first();

    $invitation = app(InviteMember::class)->handle($this->team, $this->owner, 'auditor@acme.example', $role);

    Notification::assertSentOnDemand(WorkspaceInvitation::class, function (WorkspaceInvitation $notification) use ($invitation): bool {
        return $invitation->token === hash('sha256', $notification->token)
            && TeamInvitation::findByToken($notification->token)?->is($invitation);
    });
});

it('does not let an Admin make anyone Owner or demote the Owner (M3)', function () {
    $this->actingInWorkspace($this->admin, $this->team);
    $ownerRole = Role::forTeam($this->team)->where('name', PermissionCatalog::OWNER)->first();
    $viewerRole = Role::forTeam($this->team)->where('name', PermissionCatalog::VIEWER)->first();
    $adminMembership = Membership::query()->where('team_id', $this->team->id)->where('user_id', $this->admin->id)->first();
    $ownerMembership = Membership::query()->where('team_id', $this->team->id)->where('user_id', $this->owner->id)->first();

    expect(MemberResource::roleOptions())->not->toContain(PermissionCatalog::OWNER)
        ->and(fn () => app(ChangeMemberRole::class)->handle($adminMembership, $ownerRole, $this->admin))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ChangeMemberRole::class)->handle($ownerMembership, $viewerRole, $this->admin))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(InviteMember::class)->handle($this->team, $this->admin, 'friend@x.example', $ownerRole))->toThrow(InvalidArgumentException::class);

    $this->actingInWorkspace($this->owner, $this->team);
    expect(MemberResource::roleOptions())->toContain(PermissionCatalog::OWNER);
});

it('re-checks permissions when board modals are called directly (M1)', function () {
    $stages = Stage::query()->where('team_id', $this->team->id)->get()->keyBy('name');
    $deal = Deal::factory()->forTeam($this->team, 'Proposal')->create(['owner_id' => $this->owner->id]);
    $lead = Lead::factory()->forTeam($this->team)->create(['owner_id' => $this->owner->id]);
    $this->actingInWorkspace($this->rep, $this->team);

    livewire(DealsBoard::class)
        ->mountAction('markLost', ['deal' => $deal->id, 'stage' => $stages['Lost']->id, 'position' => 0])
        ->setActionData(['lost_reason' => 'Sabotage'])
        ->callMountedAction()
        ->assertForbidden();

    livewire(LeadsBoard::class)
        ->mountAction('markUnqualified', ['lead' => $lead->id, 'position' => 0])
        ->setActionData(['reason' => 'Spam'])
        ->callMountedAction()
        ->assertForbidden();

    expect($deal->fresh()->closed_at)->toBeNull()
        ->and($lead->fresh()->unqualified_reason)->toBeNull();
});

it('lets only people who can edit the deal change its products (M2)', function () {
    $deal = Deal::factory()->forTeam($this->team)->create(['owner_id' => $this->owner->id]);
    $viewer = $this->memberOf($this->team, PermissionCatalog::VIEWER);

    foreach ([$viewer, $this->rep] as $user) {
        $this->actingInWorkspace($user, $this->team);

        livewire(LineItemsRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => ViewDeal::class])
            ->assertTableActionHidden('create');
    }

    $this->actingInWorkspace($this->admin, $this->team);
    livewire(LineItemsRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => ViewDeal::class])
        ->assertTableActionVisible('create');
});

it('neutralises spreadsheet formulas in exports (M4)', function () {
    $column = collect(ContactExporter::getColumns())->first(fn (ExportColumn $column): bool => $column->getName() === 'first_name');

    expect($column->shouldPreventFormulaInjection())->toBeTrue();
});

it('does not let an import overwrite records the importer may not edit (L2)', function () {
    $theirs = Contact::factory()->forTeam($this->team)->withEmail('vip@acme.example')->create(['first_name' => 'Original', 'owner_id' => $this->owner->id]);
    $this->actingInWorkspace($this->rep, $this->team);

    $import = Import::query()->create(['file_name' => 'x.csv', 'file_path' => 'x', 'importer' => ContactImporter::class, 'total_rows' => 1, 'user_id' => $this->rep->id]);

    ContactImporter::test(import: $import, options: ['duplicates' => 'update'])
        ->import(['first_name' => 'Hijacked', 'email' => 'vip@acme.example'])
        ->assertHasRowFailure();

    expect($theirs->fresh()->first_name)->toBe('Original');
});

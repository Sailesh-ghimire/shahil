<?php

use App\Enums\ActivityType;
use App\Enums\ContactMethodType;
use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->actingInWorkspace($this->owner, $this->team);
});

it('creates a contact with several emails and phones and two companies', function () {
    $everest = Company::factory()->forTeam($this->team)->create(['name' => 'Everest Logistics']);
    $nimbus = Company::factory()->forTeam($this->team)->create(['name' => 'Nimbus Software']);

    livewire(CreateContact::class)
        ->fillForm([
            'first_name' => 'Sita',
            'last_name' => 'Sharma',
            'owner_id' => $this->owner->id,
            'methods' => [
                ['type' => 'email', 'value' => 'sita@everest.example', 'label' => 'Work', 'is_primary' => true],
                ['type' => 'email', 'value' => 'Sita.Sharma@Mail.example', 'label' => 'Personal', 'is_primary' => false],
                ['type' => 'whatsapp', 'value' => '980-1011111', 'label' => null, 'is_primary' => true],
            ],
            'companyLinks' => [
                ['company_id' => $everest->id, 'job_title' => 'Operations manager', 'is_primary' => true],
                ['company_id' => $nimbus->id, 'job_title' => 'Advisor', 'is_primary' => false],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Contact created');

    $sita = Contact::query()->where('first_name', 'Sita')->firstOrFail();

    expect($sita->team_id)->toBe($this->team->id)
        ->and($sita->methods)->toHaveCount(3)
        ->and($sita->methods->where('type', ContactMethodType::WhatsApp)->first()->value)->toBe('+9779801011111')
        ->and($sita->methods->where('label', 'Personal')->first()->normalized_value)->toBe('sita.sharma@mail.example')
        ->and($sita->companies->pluck('pivot.job_title', 'name')->all())
        ->toBe(['Everest Logistics' => 'Operations manager', 'Nimbus Software' => 'Advisor']);
});

it('finds a contact by any of its phone formats', function () {
    $contact = Contact::factory()->forTeam($this->team)->withPhone('+977 980-1011111')->create();

    expect(Contact::query()->withMethod('9801011111', ContactMethodType::Phone)->first()?->id)->toBe($contact->id)
        ->and(Contact::query()->withMethod('+977-9801011111')->first()?->id)->toBe($contact->id);
});

it('logs activities on the timeline and keeps last activity fresh', function () {
    $contact = Contact::factory()->forTeam($this->team)->create();

    livewire(ViewContact::class, ['record' => $contact->getRouteKey()])
        ->callAction('logActivity', [
            'type' => ActivityType::Call->value,
            'title' => 'Intro call',
            'body' => 'Interested in fleet tracking.',
            'happened_at' => now()->subHour()->format('Y-m-d H:i'),
        ])
        ->assertNotified('Activity logged');

    $activity = $contact->activities()->firstOrFail();

    expect($activity->type)->toBe(ActivityType::Call)
        ->and($activity->team_id)->toBe($this->team->id)
        ->and($activity->user_id)->toBe($this->owner->id)
        ->and($contact->fresh()->last_activity_at)->not->toBeNull();
});

it('lets a sales rep edit only their own contacts', function () {
    $rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $mine = Contact::factory()->forTeam($this->team)->create(['owner_id' => $rep->id]);
    $theirs = Contact::factory()->forTeam($this->team)->create(['owner_id' => $this->owner->id]);

    $this->actingInWorkspace($rep, $this->team);

    expect($rep->can('view', $theirs))->toBeTrue()
        ->and($rep->can('update', $mine))->toBeTrue()
        ->and($rep->can('update', $theirs))->toBeFalse()
        ->and($rep->can('delete', $mine))->toBeFalse();

    livewire(EditContact::class, ['record' => $theirs->getRouteKey()])->assertForbidden();
    livewire(EditContact::class, ['record' => $mine->getRouteKey()])->assertOk();
});

it('lets a viewer read but not change contacts', function () {
    $viewer = $this->memberOf($this->team, PermissionCatalog::VIEWER);
    $contact = Contact::factory()->forTeam($this->team)->create();
    $this->actingInWorkspace($viewer, $this->team);

    livewire(ListContacts::class)->assertCanSeeTableRecords([$contact]);
    livewire(CreateContact::class)->assertForbidden();
    livewire(ViewContact::class, ['record' => $contact->getRouteKey()])->assertActionHidden('logActivity');
});

it('writes changes to the audit log with the workspace id', function () {
    $contact = Contact::factory()->forTeam($this->team)->create(['first_name' => 'Ramesh']);
    $contact->update(['first_name' => 'Ramesh K.']);

    $entry = AuditLog::query()->where('subject_type', $contact->getMorphClass())->where('event', 'updated')->firstOrFail();

    expect($entry->team_id)->toBe($this->team->id)
        ->and($entry->attribute_changes->get('old')['first_name'])->toBe('Ramesh')
        ->and($entry->attribute_changes->get('attributes')['first_name'])->toBe('Ramesh K.')
        ->and($entry->causer_id)->toBe($this->owner->id);
});

it('soft deletes and restores contacts', function () {
    $contact = Contact::factory()->forTeam($this->team)->create();

    livewire(ViewContact::class, ['record' => $contact->getRouteKey()])->callAction('delete');
    expect($contact->fresh()->trashed())->toBeTrue();

    livewire(ViewContact::class, ['record' => $contact->getRouteKey()])->callAction('restore');
    expect($contact->fresh()->trashed())->toBeFalse();
});

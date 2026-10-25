<?php

use App\Enums\ActivityType;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Workspace\CurrentWorkspace;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->acme = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->himalaya = $this->createWorkspace(name: 'Himalaya Exports');

    $this->acmeCompany = Company::factory()->forTeam($this->acme)->create(['name' => 'Everest Logistics']);
    $this->acmeContact = Contact::factory()->forTeam($this->acme)->withEmail('sita@everest.example')->create();
    $this->himalayaCompany = Company::factory()->forTeam($this->himalaya)->create(['name' => 'Annapurna Tea']);
    $this->himalayaContact = Contact::factory()->forTeam($this->himalaya)->withEmail('tenzing@annapurna.example')->create();

    $this->actingInWorkspace($this->owner, $this->acme);
});

it('lists only this workspace’s companies and contacts', function () {
    livewire(ListCompanies::class)
        ->assertCanSeeTableRecords([$this->acmeCompany])
        ->assertCanNotSeeTableRecords([$this->himalayaCompany]);

    livewire(ListContacts::class)
        ->assertCanSeeTableRecords([$this->acmeContact])
        ->assertCanNotSeeTableRecords([$this->himalayaContact]);
});

it('does not find the other workspace’s contacts by search', function () {
    livewire(ListContacts::class)
        ->searchTable('tenzing@annapurna.example')
        ->assertCanNotSeeTableRecords([$this->himalayaContact]);

    livewire(ListContacts::class)
        ->searchTable('sita@everest')
        ->assertCanSeeTableRecords([$this->acmeContact]);
});

it('returns 404 for the other workspace’s record URLs', function () {
    $this->get("/app/acme-traders/companies/{$this->himalayaCompany->id}")->assertNotFound();
    $this->get("/app/acme-traders/companies/{$this->himalayaCompany->id}/edit")->assertNotFound();
    $this->get("/app/acme-traders/contacts/{$this->himalayaContact->id}")->assertNotFound();
    $this->get("/app/acme-traders/contacts/{$this->himalayaContact->id}/edit")->assertNotFound();

    $this->get("/app/acme-traders/companies/{$this->acmeCompany->id}")->assertOk()->assertSee('Everest Logistics');
});

it('scopes models outside the panel through the workspace context', function () {
    Filament::setTenant(null);
    $context = app(CurrentWorkspace::class);

    $context->run($this->himalaya, function () {
        expect(Contact::query()->pluck('id')->all())->toBe([$this->himalayaContact->id])
            ->and(Company::query()->pluck('name')->all())->toBe(['Annapurna Tea']);
    });
});

it('shows only this workspace’s audit entries', function () {
    livewire(ListAuditLogs::class)
        ->assertCanSeeTableRecords(AuditLog::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->get())
        ->assertCanNotSeeTableRecords(AuditLog::query()->withoutGlobalScopes()->where('team_id', $this->himalaya->id)->get());
});

it('stamps timeline entries with their record’s workspace', function () {
    app(CurrentWorkspace::class)->run($this->himalaya, fn () => $this->himalayaContact->logActivity(ActivityType::Note, 'Hello'));

    expect(Activity::query()->withoutGlobalScopes()->where('subject_id', $this->himalayaContact->id)->value('team_id'))
        ->toBe($this->himalaya->id);
});

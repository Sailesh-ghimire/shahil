<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;

/**
 * Renders every panel page against the demo data, as each role, so a broken page fails CI.
 */
beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->acme = Team::query()->where('slug', 'acme-traders')->firstOrFail();
});

it('renders every page for the owner', function () {
    $aarav = User::query()->where('email', 'aarav@acme.example')->firstOrFail();
    $company = Company::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->where('name', 'Everest Logistics')->firstOrFail();
    $contact = Contact::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->where('first_name', 'Sita')->firstOrFail();

    $deal = Deal::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->where('title', 'Fleet tracking rollout')->firstOrFail();
    $lead = Lead::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->where('name', 'Rojina Basnet')->firstOrFail();
    $pipeline = Pipeline::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->firstOrFail();
    $product = Product::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->firstOrFail();

    $this->actingAs($aarav);

    foreach ([
        '/app/acme-traders/reports',
        '/app/acme-traders/inbox',
        '/app/acme-traders/channels',
        '/app/acme-traders/tasks',
        '/app/acme-traders/leads',
        '/app/acme-traders/leads/board',
        '/app/acme-traders/leads/create',
        "/app/acme-traders/leads/{$lead->id}",
        "/app/acme-traders/leads/{$lead->id}/edit",
        '/app/acme-traders/deals',
        '/app/acme-traders/deals/board',
        '/app/acme-traders/deals/create',
        "/app/acme-traders/deals/{$deal->id}",
        "/app/acme-traders/deals/{$deal->id}/edit",
        '/app/acme-traders/products',
        '/app/acme-traders/products/create',
        "/app/acme-traders/products/{$product->id}/edit",
        '/app/acme-traders/product-categories',
        '/app/acme-traders/pipelines',
        "/app/acme-traders/pipelines/{$pipeline->id}/edit",
        '/app/acme-traders',
        '/app/acme-traders/companies',
        '/app/acme-traders/companies/create',
        "/app/acme-traders/companies/{$company->id}",
        "/app/acme-traders/companies/{$company->id}/edit",
        '/app/acme-traders/contacts',
        '/app/acme-traders/contacts/create',
        "/app/acme-traders/contacts/{$contact->id}",
        "/app/acme-traders/contacts/{$contact->id}/edit",
        '/app/acme-traders/members',
        '/app/acme-traders/roles',
        '/app/acme-traders/audit-log',
        '/app/acme-traders/profile',
        '/app/profile',
    ] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get("/app/acme-traders/contacts/{$contact->id}")
        ->assertSee('Sita Sharma')
        ->assertSee('Operations manager')
        ->assertSee('+9779801011111');

    $this->get('/app/acme-traders/deals/board')
        ->assertSee('Fleet tracking rollout')
        ->assertSee('Rs 8,30,000')
        ->assertSee('8d idle');

    $this->get("/app/acme-traders/deals/{$deal->id}")
        ->assertSee('Rs 8,30,000')
        ->assertSee('Products');
});

it('shows a sales rep their follow-ups and dashboard', function () {
    $meera = User::query()->where('email', 'meera@acme.example')->firstOrFail();

    $this->actingAs($meera);

    $this->get('/app/acme-traders/tasks')->assertOk()->assertSee('Call Sita about the proposal');
    $this->get('/app/acme-traders')->assertOk();
    $this->get('/app/acme-traders/reports')->assertOk()->assertSee('You see your own numbers.');
});

it('renders the read pages for a viewer and hides settings', function () {
    $nisha = User::query()->where('email', 'nisha@acme.example')->firstOrFail();
    $contact = Contact::query()->withoutGlobalScopes()->where('team_id', $this->acme->id)->where('first_name', 'Sita')->firstOrFail();

    $this->actingAs($nisha);

    $this->get('/app/acme-traders/contacts')->assertOk();
    $this->get("/app/acme-traders/contacts/{$contact->id}")->assertOk();
    $this->get('/app/acme-traders/contacts/create')->assertForbidden();
    $this->get('/app/acme-traders/audit-log')->assertForbidden();
    $this->get('/app/acme-traders/members')->assertForbidden();
});

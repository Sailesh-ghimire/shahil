<?php

use App\Enums\ActivityType;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Filament\Resources\Deals\Pages\DealsBoard;
use App\Filament\Resources\Deals\Pages\ViewDeal;
use App\Filament\Resources\Deals\RelationManagers\LineItemsRelationManager;
use App\Filament\Resources\Leads\Pages\LeadsBoard;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $this->actingInWorkspace($this->rep, $this->team);
    $this->stages = Stage::query()->where('team_id', $this->team->id)->orderBy('sort')->get()->keyBy('name');
});

it('takes a lead from New to Won entirely by clicking', function () {
    $lead = Lead::factory()->forTeam($this->team)->create([
        'name' => 'Rojina Basnet',
        'company_name' => 'Hotel Yak View',
        'email' => 'rojina@yakview.example',
        'estimated_value' => 420_000_00,
        'owner_id' => $this->rep->id,
    ]);
    $tracker = Product::factory()->forTeam($this->team)->create(['name' => 'GPS tracker unit', 'unit_price' => 8_500_00]);

    livewire(LeadsBoard::class)
        ->call('moveLead', $lead->id, 0, LeadStatus::Contacted->value)
        ->assertNotified('Lead status changed')
        ->call('moveLead', $lead->id, 0, LeadStatus::Qualified->value);

    expect($lead->fresh()->status)->toBe(LeadStatus::Qualified);

    livewire(ViewLead::class, ['record' => $lead->getRouteKey()])
        ->callAction('convert', ['deal_title' => 'Hotel Yak View booking system', 'create_company' => true])
        ->assertNotified('Lead converted');

    $lead->refresh();
    $deal = Deal::query()->findOrFail($lead->converted_deal_id);

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->convertedContact->primaryEmail())->toBe('rojina@yakview.example')
        ->and($lead->convertedCompany->name)->toBe('Hotel Yak View')
        ->and($deal->stage->name)->toBe('Prospecting')
        ->and($deal->amount)->toBe(420_000_00)
        ->and($deal->owner_id)->toBe($this->rep->id);

    livewire(LineItemsRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => ViewDeal::class])
        ->callTableAction('create', data: ['product_id' => $tracker->id, 'name' => 'GPS tracker unit', 'quantity' => 20, 'unit_price' => 8500, 'discount' => 10, 'tax_rate' => 13])
        ->assertNotified('Deal value updated');

    expect($deal->fresh()->amount)->toBe(153_000_00);

    livewire(DealsBoard::class)
        ->call('moveDeal', $deal->id, 0, $this->stages['Proposal']->id)
        ->assertNotified('Deal moved')
        ->call('moveDeal', $deal->id, 0, $this->stages['Negotiation']->id)
        ->call('moveDeal', $deal->id, 0, $this->stages['Won']->id)
        ->assertActionMounted('markWon')
        ->setActionData(['closed_at' => now()->toDateString()])
        ->callMountedAction();

    $deal->refresh();

    expect($deal->stage->name)->toBe('Won')
        ->and($deal->closed_at)->not->toBeNull()
        ->and($deal->activities()->where('type', ActivityType::StageChange)->count())->toBe(3);
});

it('asks for a reason when a deal is dropped on Lost, and undo puts it back', function () {
    $deal = Deal::factory()->forTeam($this->team, 'Proposal')->create(['owner_id' => $this->rep->id]);

    livewire(DealsBoard::class)
        ->call('moveDeal', $deal->id, 0, $this->stages['Lost']->id)
        ->assertActionMounted('markLost')
        ->setActionData(['lost_reason' => 'Price too high'])
        ->callMountedAction()
        ->assertNotified('Deal moved');

    expect($deal->fresh()->lost_reason)->toBe('Price too high')
        ->and($deal->fresh()->closed_at)->not->toBeNull();

    livewire(DealsBoard::class)->dispatch('undoDealMove', $deal->id, $this->stages['Proposal']->id);

    expect($deal->fresh()->stage_id)->toBe($this->stages['Proposal']->id)
        ->and($deal->fresh()->closed_at)->toBeNull()
        ->and($deal->fresh()->lost_reason)->toBeNull();
});

it('asks for a reason when a lead is dropped on Unqualified', function () {
    $lead = Lead::factory()->forTeam($this->team)->create(['owner_id' => $this->rep->id]);

    livewire(LeadsBoard::class)
        ->call('moveLead', $lead->id, 0, LeadStatus::Unqualified->value)
        ->assertActionMounted('markUnqualified')
        ->setActionData(['reason' => 'Spam'])
        ->callMountedAction();

    expect($lead->fresh()->status)->toBe(LeadStatus::Unqualified)
        ->and($lead->fresh()->unqualified_reason)->toBe('Spam');
});

it('does not let a rep move someone else’s deal', function () {
    $deal = Deal::factory()->forTeam($this->team)->create(['owner_id' => $this->owner->id]);

    livewire(DealsBoard::class)
        ->call('moveDeal', $deal->id, 0, $this->stages['Proposal']->id)
        ->assertNotified('You can only move your own deals');

    expect($deal->fresh()->stage->name)->toBe('Prospecting');
});

it('links a converted lead to an existing contact with the same phone', function () {
    $sita = Contact::factory()->forTeam($this->team)->create(['first_name' => 'Sita']);
    $sita->methods()->create(['type' => ContactMethodType::WhatsApp, 'value' => '+977 980-1011111']);
    $lead = Lead::factory()->forTeam($this->team)->create(['email' => null, 'phone' => '9801011111', 'owner_id' => $this->rep->id]);

    livewire(ViewLead::class, ['record' => $lead->getRouteKey()])
        ->callAction('convert', ['deal_title' => 'Fleet', 'create_company' => false]);

    expect($lead->fresh()->converted_contact_id)->toBe($sita->id)
        ->and(Contact::query()->count())->toBe(1);
});

it('keeps converted leads read-only', function () {
    $lead = Lead::factory()->forTeam($this->team)->status(LeadStatus::Converted)->create(['owner_id' => $this->rep->id]);

    expect($this->rep->can('update', $lead))->toBeFalse()
        ->and($this->rep->can('convert', $lead))->toBeFalse();

    livewire(ViewLead::class, ['record' => $lead->getRouteKey()])->assertActionHidden('convert');
});

it('flags deals with no activity for 7 days as stale', function () {
    $fresh = Deal::factory()->forTeam($this->team)->create();
    $stale = Deal::factory()->forTeam($this->team)->create(['stage_changed_at' => now()->subDays(9)]);

    expect($fresh->isStale())->toBeFalse()
        ->and($stale->fresh()->isStale())->toBeTrue()
        ->and($stale->fresh()->daysIdle())->toBe(9);

    $stale->logActivity(ActivityType::Call, 'Checked in');

    expect($stale->fresh()->isStale())->toBeFalse();
});

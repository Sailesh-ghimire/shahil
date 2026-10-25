<?php

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Filament\Pages\Reports;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Reports\ReportFilters;
use App\Support\Reports\SalesMetrics;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $this->actingInWorkspace($this->owner, $this->team);

    $stages = Stage::query()->where('team_id', $this->team->id)->get()->keyBy('name');

    Deal::factory()->forTeam($this->team, 'Won')->create(['owner_id' => $this->rep->id, 'amount' => 500_000_00, 'closed_at' => now()->subDays(2), 'created_at' => now()->subDays(12)]);
    Deal::factory()->forTeam($this->team, 'Won')->create(['owner_id' => $this->owner->id, 'amount' => 300_000_00, 'closed_at' => now()->subDay(), 'created_at' => now()->subDays(5)]);
    Deal::factory()->forTeam($this->team, 'Lost')->create(['owner_id' => $this->rep->id, 'amount' => 100_000_00, 'closed_at' => now()->subDay(), 'lost_reason' => 'Price too high']);
    Deal::factory()->forTeam($this->team, 'Proposal')->create(['owner_id' => $this->rep->id, 'amount' => 250_000_00]);
    Lead::factory()->forTeam($this->team)->count(3)->create(['source' => 'whatsapp', 'owner_id' => $this->rep->id]);
    Lead::factory()->forTeam($this->team)->status(LeadStatus::Qualified)->create(['source' => 'website', 'owner_id' => $this->rep->id]);

    $this->filters = fn (?int $ownerId = null) => new ReportFilters(now()->subMonth(), now(), $ownerId, null);
});

it('calculates revenue, win rate, days to close and open pipeline', function () {
    $metrics = new SalesMetrics(($this->filters)());

    expect($metrics->revenueWon())->toBe(800_000_00)
        ->and($metrics->winRate())->toBe(66.7)
        ->and($metrics->averageDaysToClose())->toBe(7.0)
        ->and($metrics->openPipelineValue())->toBe(250_000_00)
        ->and($metrics->lostReasons())->toBe(['Price too high' => 1])
        ->and($metrics->leadSources())->toBe(['whatsapp' => 3, 'website' => 1])
        ->and($metrics->funnel())->toBe(['Leads received' => 4, 'Contacted' => 1, 'Qualified' => 1, 'Converted to deal' => 0, 'Won' => 0]);
});

it('limits a rep’s numbers to their own deals', function () {
    $metrics = new SalesMetrics(($this->filters)($this->rep->id));

    expect($metrics->revenueWon())->toBe(500_000_00)
        ->and($metrics->winRate())->toBe(50.0)
        ->and($metrics->wonLostByRep()->pluck('won', 'name')->all())->toBe([$this->rep->name => 1]);
});

it('locks the owner filter for people who may only see their own reports', function () {
    $this->actingInWorkspace($this->rep, $this->team);

    expect(ReportFilters::lockedOwnerId())->toBe($this->rep->id)
        ->and(ReportFilters::fromPage(['owner_id' => $this->owner->id])->ownerId)->toBe($this->rep->id);

    $this->actingInWorkspace($this->owner, $this->team);

    expect(ReportFilters::fromPage(['owner_id' => $this->rep->id])->ownerId)->toBe($this->rep->id)
        ->and(ReportFilters::fromPage([])->ownerId)->toBeNull();
});

it('scores reps on a radar against the best rep', function () {
    Deal::query()->first()->logActivity(ActivityType::Call, 'x', $this->rep);

    $scores = (new SalesMetrics(($this->filters)()))->repScorecard()->keyBy('name');

    expect($scores[$this->rep->name]->scores[0])->toEqual(100.0)
        ->and($scores[$this->rep->name]->scores[3])->toEqual(50.0);
});

it('renders the reports page and every widget', function () {
    $this->get('/app/acme-traders/reports')->assertOk()->assertSee('Reports');

    livewire(Reports::class)->assertOk();

    foreach ((new Reports)->getWidgets() as $widget) {
        livewire($widget, ['pageFilters' => ['from' => now()->subMonth()->toDateString(), 'until' => now()->toDateString()]])->assertOk();
    }
});

it('hides reports from members without a reports permission', function () {
    $custom = Role::forTeam($this->team)->where('name', PermissionCatalog::VIEWER)->first();
    $custom->revokePermissionTo('reports.view.team');
    $viewer = $this->memberOf($this->team, PermissionCatalog::VIEWER);

    $this->actingAs($viewer)->get('/app/acme-traders/reports')->assertForbidden();
});

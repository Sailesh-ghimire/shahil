<?php

use App\Actions\Sales\AssignLeadOwner;
use App\Enums\StageKind;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\Deals\Pages\DealsBoard;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Pipelines\Pages\CreatePipeline;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions\PermissionCatalog;
use Filament\Actions\Imports\Models\Import;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->actingInWorkspace($this->owner, $this->team);
});

it('gives every new workspace a Sales pipeline with five stages', function () {
    $pipeline = Pipeline::query()->where('team_id', $this->team->id)->sole();

    expect($pipeline->name)->toBe('Sales')
        ->and($pipeline->is_default)->toBeTrue()
        ->and($pipeline->stages->pluck('name')->all())->toBe(['Prospecting', 'Proposal', 'Negotiation', 'Won', 'Lost'])
        ->and($pipeline->stages->pluck('probability')->all())->toBe([10, 40, 70, 100, 0]);
});

it('requires exactly one Won and one Lost stage in a pipeline', function () {
    livewire(CreatePipeline::class)
        ->fillForm([
            'name' => 'Renewals',
            'stages' => [
                ['name' => 'Due', 'probability' => 50, 'kind' => StageKind::Open->value],
                ['name' => 'Renewed', 'probability' => 100, 'kind' => StageKind::Won->value],
                ['name' => 'Also renewed', 'probability' => 100, 'kind' => StageKind::Won->value],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['stages']);

    livewire(CreatePipeline::class)
        ->fillForm([
            'name' => 'Renewals',
            'is_default' => false,
            'stages' => [
                ['name' => 'Due', 'probability' => 50, 'kind' => StageKind::Open->value],
                ['name' => 'Renewed', 'probability' => 100, 'kind' => StageKind::Won->value],
                ['name' => 'Churned', 'probability' => 0, 'kind' => StageKind::Lost->value],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $renewals = Pipeline::query()->where('name', 'Renewals')->sole();

    expect($renewals->stages)->toHaveCount(3)
        ->and($renewals->stages->every(fn ($stage) => $stage->team_id === $this->team->id))->toBeTrue()
        ->and(Pipeline::query()->where('is_default', true)->count())->toBe(1);
});

it('creates a product with 13% VAT by default and a SKU unique per workspace', function () {
    livewire(CreateProduct::class)
        ->fillForm(['name' => 'GPS tracker unit', 'sku' => 'HW-GPS-02', 'unit_price' => 8500])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('sku', 'HW-GPS-02')->sole();

    expect($product->unit_price)->toBe(8_500_00)
        ->and((float) $product->tax_rate)->toBe(13.0);

    livewire(CreateProduct::class)
        ->fillForm(['name' => 'Duplicate', 'sku' => 'HW-GPS-02', 'unit_price' => 1])
        ->call('create')
        ->assertHasFormErrors(['sku' => 'unique']);

    $other = $this->createWorkspace(name: 'Himalaya Exports');
    Product::factory()->forTeam($other)->create(['sku' => 'HW-GPS-02']);

    expect(Product::query()->withoutGlobalScopes()->where('sku', 'HW-GPS-02')->count())->toBe(2);
});

it('imports products with prices in rupees', function () {
    $import = Import::query()->create(['file_name' => 'products.csv', 'file_path' => 'x', 'importer' => ProductImporter::class, 'total_rows' => 1, 'user_id' => $this->owner->id]);

    ProductImporter::test(import: $import, options: ['duplicates' => 'update'])
        ->import(['sku' => 'SV-SUP-12', 'name' => 'Support plan (12 months)', 'category' => 'Services', 'unit_price' => '60,000', 'tax_rate' => '', 'is_active' => '1'])
        ->assertImported();

    $product = Product::query()->where('sku', 'SV-SUP-12')->sole();

    expect($product->unit_price)->toBe(60_000_00)
        ->and($product->category->name)->toBe('Services');
});

it('assigns new leads round-robin across sales reps', function () {
    $meera = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $dipika = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $this->memberOf($this->team, PermissionCatalog::VIEWER);

    $owners = collect(range(1, 4))->map(function () {
        $lead = Lead::factory()->forTeam($this->team)->create(['owner_id' => null]);

        return app(AssignLeadOwner::class)->handle($lead)?->id;
    })->all();

    expect($owners)->toBe([$meera->id, $dipika->id, $meera->id, $dipika->id]);
});

it('formats money in rupees with Indian grouping', function () {
    expect(Money::format(1_50_000_00))->toBe('Rs 1,50,000')
        ->and(Money::format(12_34_56_789_00))->toBe('Rs 12,34,56,789')
        ->and(Money::format(999_00))->toBe('Rs 999')
        ->and(Money::short(12_50_000_00))->toBe('Rs 12.5 lakh')
        ->and(Money::toMinor('1,500.5'))->toBe(1_500_50)
        ->and(Money::format(1_500_00, 'USD'))->toBe('USD 1,500');
});

it('keeps leads, deals and products inside their workspace', function () {
    $other = $this->createWorkspace(name: 'Himalaya Exports');
    $this->actingInWorkspace($this->owner, $this->team);

    $mine = [Lead::factory()->forTeam($this->team)->create(), Deal::factory()->forTeam($this->team)->create(), Product::factory()->forTeam($this->team)->create()];
    $theirs = [Lead::factory()->forTeam($other)->create(), Deal::factory()->forTeam($other)->create(), Product::factory()->forTeam($other)->create()];

    livewire(ListLeads::class)->assertCanSeeTableRecords([$mine[0]])->assertCanNotSeeTableRecords([$theirs[0]]);
    livewire(ListDeals::class)->assertCanSeeTableRecords([$mine[1]])->assertCanNotSeeTableRecords([$theirs[1]]);
    livewire(ListProducts::class)->assertCanSeeTableRecords([$mine[2]])->assertCanNotSeeTableRecords([$theirs[2]]);

    livewire(DealsBoard::class)->assertSee($mine[1]->title)->assertDontSee($theirs[1]->title);

    $this->get("/app/acme-traders/deals/{$theirs[1]->id}")->assertNotFound();
    $this->get("/app/acme-traders/leads/{$theirs[0]->id}")->assertNotFound();
});

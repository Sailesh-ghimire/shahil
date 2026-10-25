<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\ContactMethodType;
use App\Enums\RecordSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Business records for Himalaya Exports, matching the panel preview.
 */
class HimalayaDemoDataSeeder extends Seeder
{
    /**
     * @var array<string, Company>
     */
    public array $companies = [];

    /**
     * @var array<string, Contact>
     */
    public array $contacts = [];

    /**
     * @param  Collection<string, User>  $users
     */
    public function run(Team $team, Collection $users): void
    {
        if (Company::query()->where('team_id', $team->id)->exists()) {
            return;
        }

        $this->companies['annapurna'] = Company::query()->create(['team_id' => $team->id, 'name' => 'Annapurna Tea Estates', 'industry' => 'Agriculture', 'address' => 'Ilam', 'website' => 'https://annapurnatea.example', 'owner_id' => $users['dipika']->id]);
        $this->companies['patan'] = Company::query()->create(['team_id' => $team->id, 'name' => 'Patan Handicrafts', 'industry' => 'Retail', 'address' => 'Lalitpur', 'website' => 'https://patancrafts.example', 'owner_id' => $users['aarav']->id]);

        $vip = Tag::query()->create(['team_id' => $team->id, 'name' => 'VIP']);

        $tenzing = Contact::query()->create(['team_id' => $team->id, 'first_name' => 'Tenzing', 'last_name' => 'Bhutia', 'owner_id' => $users['dipika']->id, 'source' => RecordSource::Referral]);
        $tenzing->methods()->create(['team_id' => $team->id, 'type' => ContactMethodType::Email, 'value' => 'tenzing@annapurnatea.example', 'is_primary' => true]);
        $tenzing->methods()->create(['team_id' => $team->id, 'type' => ContactMethodType::Phone, 'value' => '+977 984-1112222', 'is_primary' => true]);
        $tenzing->companies()->attach($this->companies['annapurna']->id, ['team_id' => $team->id, 'job_title' => 'Export manager', 'is_primary' => true]);
        $tenzing->logActivity(ActivityType::Call, 'Discussed container schedule for December.', $users['dipika']);

        $laxmi = Contact::query()->create(['team_id' => $team->id, 'first_name' => 'Laxmi', 'last_name' => 'Maharjan', 'owner_id' => $users['aarav']->id, 'source' => RecordSource::Manual]);
        $laxmi->methods()->create(['team_id' => $team->id, 'type' => ContactMethodType::Email, 'value' => 'laxmi@patancrafts.example', 'is_primary' => true]);
        $laxmi->companies()->attach($this->companies['patan']->id, ['team_id' => $team->id, 'job_title' => 'Owner', 'is_primary' => true]);
        $laxmi->tags()->sync([$vip->id]);

        $this->contacts = ['tenzing' => $tenzing, 'laxmi' => $laxmi];

        $this->seedSales($team, $users);
    }

    /**
     * Himalaya Exports uses its own stages: Enquiry, Sample sent, Order confirmed, Won, Lost.
     *
     * @param  Collection<string, User>  $users
     */
    protected function seedSales(Team $team, Collection $users): void
    {
        $pipeline = Pipeline::query()->where('team_id', $team->id)->firstOrFail();
        $pipeline->update(['name' => 'Export orders']);

        $renames = ['Prospecting' => ['Enquiry', 10], 'Proposal' => ['Sample sent', 40], 'Negotiation' => ['Order confirmed', 80]];

        foreach ($pipeline->stages as $stage) {
            if (isset($renames[$stage->name])) {
                $stage->update(['name' => $renames[$stage->name][0], 'probability' => $renames[$stage->name][1]]);
            }
        }

        $stages = $pipeline->stages()->get()->keyBy('name');
        $tea = Product::query()->create(['team_id' => $team->id, 'sku' => 'TEA-ORG-1KG', 'name' => 'Organic orthodox tea, 1 kg', 'unit_price' => 3_200_00]);
        $bowl = Product::query()->create(['team_id' => $team->id, 'sku' => 'CRF-SB-01', 'name' => 'Singing bowl, medium', 'unit_price' => 5_500_00]);

        foreach ([
            ['Germany tea container', 'annapurna', 'tenzing', 'Sample sent', 'dipika', $tea, 400],
            ['Hotel gift bowls', 'patan', 'laxmi', 'Enquiry', 'aarav', $bowl, 60],
        ] as [$title, $companyKey, $contactKey, $stageName, $ownerKey, $product, $quantity]) {
            $deal = Deal::query()->create([
                'team_id' => $team->id,
                'title' => $title,
                'company_id' => $this->companies[$companyKey]->id,
                'contact_id' => $this->contacts[$contactKey]->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stages[$stageName]->id,
                'owner_id' => $users[$ownerKey]->id,
                'expected_close_on' => now()->addWeeks(4),
            ]);

            $deal->lineItems()->create(['team_id' => $team->id, 'product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'unit_price' => $product->unit_price, 'tax_rate' => 13]);
        }

        Lead::query()->create(['team_id' => $team->id, 'name' => 'Jonas Weber', 'company_name' => 'Teehaus Berlin', 'email' => 'jonas@teehaus.example', 'source' => RecordSource::Website, 'estimated_value' => 900_000_00, 'owner_id' => $users['dipika']->id]);
    }
}

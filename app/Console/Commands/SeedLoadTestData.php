<?php

namespace App\Console\Commands;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Pipeline;
use App\Models\Team;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills a "Load Test" workspace with plan-sized data (50,000 contacts by default) using bulk inserts,
 * to check list pages and boards stay fast. Never run on production data.
 */
class SeedLoadTestData extends Command
{
    protected $signature = 'crm:seed-load-test {--contacts=50000 : Number of contacts} {--force : Run outside local/testing}';

    protected $description = 'Create a large "Load Test" workspace for performance checks';

    public function handle(CreateWorkspace $createWorkspace): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->components->error('Refusing to run outside local/testing without --force.');

            return self::FAILURE;
        }

        $contacts = max(100, (int) $this->option('contacts'));
        $owner = User::query()->firstOrNew(['email' => 'loadtest@example.test']);

        if (! $owner->exists) {
            $owner->forceFill(['name' => 'Load Tester', 'password' => 'password', 'email_verified_at' => now()])->save();
        }

        $team = Team::query()->where('slug', 'load-test')->first()
            ?? $createWorkspace->handle($owner, ['name' => 'Load Test']);

        $teamId = $team->getKey();
        $pipeline = Pipeline::query()->withoutGlobalScopes()->where('team_id', $teamId)->firstOrFail();
        $stageIds = $pipeline->stages()->withoutGlobalScopes()->pluck('id')->all();
        $now = now()->toDateTimeString();

        $this->components->info("Seeding {$contacts} contacts into workspace #{$teamId}…");

        $companyCount = (int) ceil($contacts / 10);
        $this->bulk('companies', $companyCount, fn (int $i): array => [
            'team_id' => $teamId, 'name' => 'Company '.$i, 'industry' => ['Logistics', 'FMCG', 'Travel', 'Retail'][$i % 4],
            'owner_id' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
            'last_activity_at' => now()->subMinutes($i)->toDateTimeString(),
        ]);
        $firstCompanyId = (int) DB::table('companies')->where('team_id', $teamId)->min('id');

        $this->bulk('contacts', $contacts, fn (int $i): array => [
            'team_id' => $teamId, 'first_name' => 'First'.$i, 'last_name' => 'Last'.$i, 'owner_id' => $owner->id,
            'source' => 'import', 'created_at' => $now, 'updated_at' => $now,
            'last_activity_at' => now()->subMinutes($i)->toDateTimeString(),
        ]);
        $firstContactId = (int) DB::table('contacts')->where('team_id', $teamId)->min('id');

        $this->bulk('contact_methods', $contacts * 2, function (int $i) use ($teamId, $firstContactId, $now): array {
            $contactId = $firstContactId + intdiv($i, 2);
            $isEmail = $i % 2 === 0;
            $value = $isEmail ? "person{$contactId}@load.example" : '+97798'.str_pad((string) $contactId, 8, '0', STR_PAD_LEFT);

            return [
                'team_id' => $teamId, 'contact_id' => $contactId, 'type' => $isEmail ? 'email' : 'phone',
                'value' => $value, 'normalized_value' => $isEmail ? $value : PhoneNumber::normalize($value),
                'is_primary' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        });

        $this->bulk('company_contact', $contacts, fn (int $i): array => [
            'team_id' => $teamId, 'company_id' => $firstCompanyId + ($i % $companyCount), 'contact_id' => $firstContactId + $i,
            'job_title' => 'Manager', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->bulk('leads', intdiv($contacts, 2), fn (int $i): array => [
            'team_id' => $teamId, 'name' => 'Lead '.$i, 'phone' => '+97797'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            'source' => ['whatsapp', 'instagram', 'facebook', 'website'][$i % 4], 'status' => ['new', 'contacted', 'qualified', 'unqualified'][$i % 4],
            'estimated_value' => ($i % 50) * 1_000_00, 'owner_id' => $owner->id, 'board_position' => $i,
            'created_at' => now()->subHours($i % 2000)->toDateTimeString(), 'updated_at' => $now,
        ]);

        $this->bulk('deals', intdiv($contacts, 2), fn (int $i): array => [
            'team_id' => $teamId, 'title' => 'Deal '.$i, 'company_id' => $firstCompanyId + ($i % $companyCount),
            'contact_id' => $firstContactId + $i, 'pipeline_id' => $pipeline->id, 'stage_id' => $stageIds[$i % count($stageIds)],
            'amount' => (($i % 90) + 10) * 10_000_00, 'currency' => 'NPR', 'owner_id' => $owner->id, 'board_position' => $i,
            'closed_at' => in_array($i % count($stageIds), [3, 4], true) ? now()->subDays($i % 200)->toDateTimeString() : null,
            'stage_changed_at' => now()->subDays($i % 30)->toDateTimeString(), 'created_at' => now()->subDays($i % 300)->toDateTimeString(), 'updated_at' => $now,
        ]);

        $this->bulk('activities', $contacts * 2, fn (int $i): array => [
            'team_id' => $teamId, 'subject_type' => 'App\\Models\\Contact', 'subject_id' => $firstContactId + ($i % $contacts),
            'type' => ['call', 'meeting', 'note', 'email'][$i % 4], 'body' => 'Load test activity '.$i,
            'happened_at' => now()->subMinutes($i)->toDateTimeString(), 'user_id' => $owner->id, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->components->info('Done. Sign in as loadtest@example.test / password and open /app/load-test.');

        return self::SUCCESS;
    }

    /**
     * @param  callable(int): array<string, mixed>  $row
     */
    protected function bulk(string $table, int $count, callable $row): void
    {
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(Str::padRight($table, 16).' %current%/%max% [%bar%] %elapsed%');

        foreach (array_chunk(range(0, $count - 1), 2000) as $chunk) {
            DB::table($table)->insert(array_map($row, $chunk));
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
    }
}

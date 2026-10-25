<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Renders the main panel pages for a workspace in-process and reports time and query count
 * (target from the plan: list pages under 500 ms at 50,000 contacts).
 */
class BenchmarkPages extends Command
{
    protected $signature = 'crm:benchmark {workspace=load-test : Workspace slug} {--email=loadtest@example.test} {--runs=3} {--slow : Print queries slower than 30 ms}';

    protected $description = 'Time the main pages of a workspace';

    public function handle(Kernel $kernel): int
    {
        $team = Team::query()->where('slug', $this->argument('workspace'))->firstOrFail();
        $user = User::query()->where('email', $this->option('email'))->firstOrFail();
        $slug = $team->slug;

        $pages = [
            'Dashboard' => "/app/{$slug}",
            'Contacts list' => "/app/{$slug}/contacts",
            'Contacts search (phone)' => "/app/{$slug}/contacts?search=9800001234",
            'Companies list' => "/app/{$slug}/companies",
            'Leads list' => "/app/{$slug}/leads",
            'Leads board' => "/app/{$slug}/leads/board",
            'Deals list' => "/app/{$slug}/deals",
            'Deals board' => "/app/{$slug}/deals/board",
            'Tasks' => "/app/{$slug}/tasks",
            'Inbox' => "/app/{$slug}/inbox",
            'Reports' => "/app/{$slug}/reports",
        ];

        $rows = [];

        foreach ($pages as $label => $url) {
            $timings = [];
            $queries = 0;
            $dbTimes = [];
            $status = 0;

            for ($run = 0; $run < (int) $this->option('runs'); $run++) {
                Auth::guard('web')->setUser($user);
                DB::flushQueryLog();
                DB::enableQueryLog();

                $start = hrtime(true);
                $response = $kernel->handle(Request::create($url));
                $timings[] = (hrtime(true) - $start) / 1e6;

                $queries = count(DB::getQueryLog());

                if ($this->option('slow') && $run === 0) {
                    foreach (DB::getQueryLog() as $query) {
                        if ($query['time'] >= 30) {
                            $this->line("<comment>{$label}</comment> {$query['time']} ms: ".str($query['query'])->limit(400));
                        }
                    }
                }
                $dbTimes[] = array_sum(array_column(DB::getQueryLog(), 'time'));
                $status = $response->getStatusCode();
                DB::disableQueryLog();
            }

            sort($timings);
            $median = $timings[intdiv(count($timings), 2)];

            sort($dbTimes);
            $rows[] = [$label, $status, number_format($median).' ms', number_format($dbTimes[intdiv(count($dbTimes), 2)]).' ms', $queries, $median < 500 ? 'ok' : 'SLOW'];
        }

        $this->table(['Page', 'HTTP', 'Median', 'DB time', 'Queries', '< 500 ms'], $rows);

        return self::SUCCESS;
    }
}

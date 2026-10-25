<?php

namespace App\Providers;

use App\Actions\Sales\CreateDefaultPipeline;
use App\Events\WorkspaceCreated;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\JobFailedAlert;
use App\Support\Workspace\CurrentWorkspace;
use Filament\Actions\Exports\ExportColumn;
use Filament\Notifications\Livewire\Notifications;
use Filament\Resources\Resource;
use Filament\Support\Assets\Css;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Workspace isolation is done by the BelongsToWorkspace scope (which follows the panel's tenant).
        // Filament's own tenancy observer would re-assign records created for another workspace.
        Resource::scopeToTenant(false);

        // The Owner of the current workspace can do everything there.
        // The Owner role itself stays read-only, even for Owners.
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (! app(CurrentWorkspace::class)->check()) {
                return null;
            }

            if (($arguments[0] ?? null) instanceof Role && in_array($ability, ['update', 'delete'], true)) {
                return null;
            }

            return $user->isWorkspaceOwner() ? true : null;
        });

        // Stamp each audit entry with its workspace: the subject's, else the current one.
        LogActivityAction::beforeLogging(function (Activity $activity): void {
            if (! $activity instanceof AuditLog || $activity->team_id !== null) {
                return;
            }

            $subject = $activity->subject;

            $activity->team_id = $subject !== null && $subject->getAttribute('team_id') !== null
                ? $subject->getAttribute('team_id')
                : app(CurrentWorkspace::class)->id();
        });

        Event::listen(WorkspaceCreated::class, fn (WorkspaceCreated $event) => app(CreateDefaultPipeline::class)->handle($event->team));

        // Error monitoring: every job that gives up is logged and, if configured, emailed to the operator.
        Queue::failing(function (JobFailed $event): void {
            Log::error('Job failed', ['job' => $event->job->resolveName(), 'queue' => $event->job->getQueue(), 'exception' => $event->exception->getMessage()]);

            if (filled(config('crm.ops_alert_email'))) {
                Notification::route('mail', (string) config('crm.ops_alert_email'))->notify(JobFailedAlert::fromEvent($event));
            }
        });

        FilamentAsset::register([
            Css::make('crm', resource_path('css/crm.css')),
        ]);

        // Names and messages come from customers: never let a cell start a spreadsheet formula.
        ExportColumn::configureUsing(fn (ExportColumn $column) => $column->preventFormulaInjection());

        // Store UTC, show each user their own timezone.
        FilamentTimezone::set(fn (): string => auth()->user()->timezone ?? config('app.timezone'));

        Notifications::alignment(Alignment::End);
        Notifications::verticalAlignment(VerticalAlignment::Start);
    }
}

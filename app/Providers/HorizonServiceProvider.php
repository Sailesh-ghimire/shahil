<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if (filled(config('crm.ops_alert_email'))) {
            Horizon::routeMailNotificationsTo((string) config('crm.ops_alert_email'));
        }
    }

    /**
     * Horizon's dashboard (/horizon) is for the people running the server, listed in HORIZON_ADMINS.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            return $user !== null && in_array(strtolower($user->email), config('crm.horizon_admins'), true);
        });
    }
}

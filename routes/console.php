<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Product;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('crm:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('crm:stale-deal-follow-ups')->hourly()->withoutOverlapping();
Schedule::command('crm:check-channel-tokens')->dailyAt('06:00');
Schedule::command('model:prune', ['--model' => [Company::class, Contact::class, Lead::class, Deal::class, Product::class, WebhookLog::class]])->daily();
Schedule::command('activitylog:clean')->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

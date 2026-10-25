<?php

use App\Models\User;
use App\Notifications\JobFailedAlert;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

it('emails the operator when a background job fails', function () {
    Notification::fake();
    config(['crm.ops_alert_email' => 'ops@example.test']);

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ProcessMetaWebhook');
    $job->shouldReceive('getQueue')->andReturn('webhooks');
    $job->shouldReceive('getJobId')->andReturn('job-1');
    $job->shouldIgnoreMissing();

    event(new JobFailed('redis', $job, new RuntimeException('Meta timed out')));

    Notification::assertSentOnDemand(JobFailedAlert::class, fn (JobFailedAlert $alert): bool => $alert->queue === 'webhooks'
        && str_contains($alert->error, 'Meta timed out'));
});

it('opens the Horizon dashboard only to the configured admins', function () {
    config(['crm.horizon_admins' => ['aarav@acme.example']]);

    $admin = User::factory()->create(['email' => 'aarav@acme.example']);
    $someone = User::factory()->create();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($someone)->allows('viewHorizon'))->toBeFalse();
});

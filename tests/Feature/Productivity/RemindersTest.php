<?php

use App\Enums\ReminderOffset;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Livewire\ReminderToasts;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);
    $this->rep->update(['timezone' => 'Asia/Kathmandu']);
    $this->actingInWorkspace($this->rep, $this->team);
});

afterEach(fn () => Carbon::setTestNow());

it('stores a follow-up in UTC from the user’s own timezone', function () {
    $contact = Contact::factory()->forTeam($this->team)->create(['owner_id' => $this->rep->id]);

    livewire(ViewContact::class, ['record' => $contact->getRouteKey()])
        ->callAction('addFollowUp', [
            'title' => 'Call Sita about the proposal',
            'type' => 'call',
            'due_at' => '2026-10-30 10:00:00',
            'remind_minutes_before' => ReminderOffset::FifteenMinutes->value,
            'assignee_id' => $this->rep->id,
        ])
        ->assertNotified('Follow-up scheduled');

    $task = Task::query()->sole();

    expect($task->due_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-30 04:15')
        ->and($task->remind_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-30 04:00')
        ->and($task->subject->is($contact))->toBeTrue()
        ->and($task->team_id)->toBe($this->team->id);
});

it('delivers a reminder set 5 minutes ahead on time, once, in the bell and by email', function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-30 04:00:00');

    $task = Task::factory()->forTeam($this->team)->create([
        'title' => 'Call Sita about the proposal',
        'due_at' => now()->addMinutes(5),
        'remind_minutes_before' => ReminderOffset::AtDueTime->value,
        'assignee_id' => $this->rep->id,
    ]);

    $this->artisan('crm:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    Carbon::setTestNow('2026-10-30 04:05:00');
    $this->artisan('crm:send-reminders')->assertSuccessful();

    Notification::assertSentTo($this->rep, TaskReminder::class, function (TaskReminder $notification, array $channels) use ($task): bool {
        return $notification->task->is($task) && $channels === ['database', 'mail'];
    });

    expect($task->fresh()->reminded_at)->not->toBeNull()
        ->and(TaskReminder::dueText($task, $this->rep))->toBe('Due today at 09:50.');

    $this->artisan('crm:send-reminders');
    Notification::assertSentToTimes($this->rep, TaskReminder::class, 1);
});

it('never sends reminders for completed tasks', function () {
    Notification::fake();

    Task::factory()->forTeam($this->team)->create([
        'due_at' => now()->subMinute(),
        'remind_minutes_before' => 0,
        'assignee_id' => $this->rep->id,
    ])->complete();

    $this->artisan('crm:send-reminders');

    Notification::assertNothingSent();
});

it('marks done and snoozes from the signed reminder links', function () {
    $task = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->rep->id, 'due_at' => now()->subMinutes(5)]);

    $this->get(TaskReminder::snoozeUrl($task))->assertRedirect();
    expect($task->fresh()->due_at->greaterThan(now()->addMinutes(55)))->toBeTrue();

    $this->get(TaskReminder::completeUrl($task))->assertRedirect('/app/acme-traders');
    expect($task->fresh()->completed_at)->not->toBeNull();

    $this->get(route('tasks.complete', $task))->assertForbidden();
});

it('pops a persistent reminder toast in the open panel', function () {
    $task = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->rep->id, 'title' => 'Demo for Nimbus']);

    $component = livewire(ReminderToasts::class);

    $task->forceFill(['reminded_at' => now()])->save();

    $component->call('check')->assertNotified('Reminder: Demo for Nimbus');

    $component->dispatch('snoozeTask', $task->id, 60);
    expect($task->fresh()->due_at->greaterThan(now()->addMinutes(55)))->toBeTrue();

    $component->dispatch('completeTask', $task->id);
    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('creates one follow-up for a deal idle beyond the workspace limit', function () {
    $stale = Deal::factory()->forTeam($this->team)->create(['owner_id' => $this->rep->id, 'stage_changed_at' => now()->subDays(8)]);
    $fresh = Deal::factory()->forTeam($this->team)->create(['owner_id' => $this->rep->id]);

    $this->artisan('crm:stale-deal-follow-ups')->assertSuccessful();
    $this->artisan('crm:stale-deal-follow-ups')->assertSuccessful();

    $tasks = Task::query()->get();

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->subject->is($stale))->toBeTrue()
        ->and($tasks->first()->assignee_id)->toBe($this->rep->id)
        ->and($tasks->first()->is_automatic)->toBeTrue()
        ->and($fresh->tasks()->count())->toBe(0);

    $this->team->update(['settings' => ['stale_deal_days' => 0]]);
    $tasks->first()->complete();
    $this->artisan('crm:stale-deal-follow-ups');

    expect(Task::query()->count())->toBe(1);
});

it('shows a rep only their own tasks, split into Today, Overdue and Upcoming', function () {
    $today = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->rep->id, 'due_at' => now('Asia/Kathmandu')->endOfDay()->subHour()->utc()]);
    $overdue = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->rep->id, 'due_at' => now()->subDays(2)]);
    $upcoming = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->rep->id, 'due_at' => now()->addDays(3)]);
    $someoneElses = Task::factory()->forTeam($this->team)->create(['assignee_id' => $this->owner->id, 'created_by' => $this->owner->id, 'due_at' => now('Asia/Kathmandu')->endOfDay()->subHour()->utc()]);

    livewire(ListTasks::class)
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$overdue, $upcoming, $someoneElses])
        ->set('activeTab', 'overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->set('activeTab', 'upcoming')
        ->assertCanSeeTableRecords([$upcoming])
        ->callTableAction('complete', $upcoming)
        ->assertNotified('Task completed');

    expect($upcoming->fresh()->completed_at)->not->toBeNull();
});

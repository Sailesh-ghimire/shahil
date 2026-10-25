<?php

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Enums\MessageStatus;
use App\Filament\Pages\Channels;
use App\Filament\Pages\Inbox;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Notifications\ChannelNeedsReconnect;
use App\Notifications\MessageNotDelivered;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Reports\ReportFilters;
use App\Support\Reports\SalesMetrics;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);

    $this->account = ChannelAccount::query()->create([
        'team_id' => $this->team->id, 'channel' => Channel::WhatsApp, 'external_id' => '111222333',
        'business_account_id' => '444555666', 'name' => 'Acme Sales', 'access_token' => 'wa-token',
    ]);

    $this->conversation = Conversation::query()->create([
        'team_id' => $this->team->id, 'channel_account_id' => $this->account->id, 'external_thread_id' => '9779811234567',
        'display_name' => 'Nabin KC', 'handle' => '+9779811234567', 'assignee_id' => $this->rep->id,
        'last_inbound_at' => now()->subMinutes(30), 'awaiting_reply_since' => now()->subMinutes(30), 'unread_count' => 2,
    ]);

    $this->actingInWorkspace($this->rep, $this->team);
});

it('sends a WhatsApp reply from the inbox and records the reply time', function () {
    Http::fake(['graph.facebook.com/*/111222333/messages' => Http::response(['messages' => [['id' => 'wamid.OUT1']]])]);

    livewire(Inbox::class)
        ->call('open', $this->conversation->id)
        ->set('reply', 'Hi Nabin, 5 seats are Rs 60,000 a year.')
        ->call('send')
        ->assertSet('reply', '');

    $message = Message::query()->where('direction', Message::OUTBOUND)->sole();

    expect($message->status)->toBe(MessageStatus::Sent)
        ->and($message->external_id)->toBe('wamid.OUT1')
        ->and($message->sent_by)->toBe($this->rep->id)
        ->and($message->response_seconds)->toBeGreaterThanOrEqual(1799)
        ->and($this->conversation->fresh()->unread_count)->toBe(0)
        ->and($this->conversation->fresh()->awaiting_reply_since)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request['to'] === '9779811234567'
        && $request['text']['body'] === 'Hi Nabin, 5 seats are Rs 60,000 a year.'
        && $request->hasHeader('Authorization', 'Bearer wa-token'));

    $metrics = new SalesMetrics(new ReportFilters(now()->subDay(), now()->addMinute(), null, null));
    expect($metrics->firstReplyMinutesByChannel()['whatsapp'])->toBeGreaterThanOrEqual(29.9);
});

it('only allows templates more than 24 hours after the customer’s last message', function () {
    Http::fake(['graph.facebook.com/*/111222333/messages' => Http::response(['messages' => [['id' => 'wamid.TPL1']]])]);
    $this->conversation->update(['last_inbound_at' => now()->subHours(30)]);
    $template = WhatsAppTemplate::query()->create([
        'team_id' => $this->team->id, 'channel_account_id' => $this->account->id, 'name' => 'follow_up',
        'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, any update on your order?', 'parameter_count' => 1,
    ]);

    livewire(Inbox::class)
        ->call('open', $this->conversation->id)
        ->set('reply', 'Free text')
        ->call('send')
        ->assertNotified('Message not sent')
        ->callAction('sendTemplate', ['template_id' => $template->id, 'parameter_1' => 'Nabin'])
        ->assertNotified('Template queued');

    $message = Message::query()->where('direction', Message::OUTBOUND)->sole();

    expect($message->template_name)->toBe('follow_up')
        ->and($message->body)->toBe('Hi Nabin, any update on your order?')
        ->and($message->status)->toBe(MessageStatus::Sent);

    Http::assertSent(fn (Request $request): bool => $request['type'] === 'template'
        && $request['template']['name'] === 'follow_up'
        && $request['template']['components'][0]['parameters'][0]['text'] === 'Nabin');
});

it('marks the account for reconnecting and tells the sender when Meta rejects the token', function () {
    Notification::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token: Session has expired', 'code' => 190]], 401)]);

    livewire(Inbox::class)->call('open', $this->conversation->id)->set('reply', 'Hello')->call('send');

    $message = Message::query()->where('direction', Message::OUTBOUND)->sole();

    expect($message->status)->toBe(MessageStatus::Failed)
        ->and($message->error)->toContain('Session has expired')
        ->and($this->account->fresh()->status)->toBe(ChannelAccountStatus::ReconnectNeeded);

    Notification::assertSentTo($this->rep, MessageNotDelivered::class);
});

it('lets a sales rep reply only to conversations assigned to them', function () {
    $this->conversation->update(['assignee_id' => $this->owner->id]);

    livewire(Inbox::class)->call('open', $this->conversation->id)->set('reply', 'Hi')->call('send')->assertForbidden();

    $this->conversation->update(['assignee_id' => null]);

    livewire(Inbox::class)
        ->call('open', $this->conversation->id)
        ->callAction('assignToMe')
        ->assertNotified('Assigned to you');

    expect($this->conversation->fresh()->assignee_id)->toBe($this->rep->id);
});

it('creates a lead from a conversation', function () {
    livewire(Inbox::class)
        ->call('open', $this->conversation->id)
        ->callAction('createLead', ['name' => 'Nabin KC', 'company_name' => 'KC Traders'])
        ->assertNotified('Lead created');

    $lead = Lead::query()->sole();

    expect($this->conversation->fresh()->lead_id)->toBe($lead->id)
        ->and($lead->phone)->toBe('+9779811234567')
        ->and($lead->company_name)->toBe('KC Traders');
});

it('shows only this workspace’s conversations', function () {
    $other = $this->createWorkspace(name: 'Himalaya Exports');
    $foreignAccount = ChannelAccount::query()->create(['team_id' => $other->id, 'channel' => Channel::WhatsApp, 'external_id' => '999', 'name' => 'H', 'access_token' => 'x']);
    $foreign = Conversation::query()->create(['team_id' => $other->id, 'channel_account_id' => $foreignAccount->id, 'external_thread_id' => '1', 'display_name' => 'Tenzing Secret']);
    $this->actingInWorkspace($this->rep, $this->team);

    livewire(Inbox::class)
        ->assertSee('Nabin KC')
        ->assertDontSee('Tenzing Secret')
        ->call('open', $foreign->id)
        ->assertSet('conversation', null);
});

it('connects a WhatsApp number after checking the token with Meta', function () {
    $this->actingInWorkspace($this->owner, $this->team);
    Http::fake(['graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => true, 'expires_at' => 0]])]);

    livewire(Channels::class)
        ->callAction('connectWhatsApp', [
            'name' => 'Acme Support',
            'external_id' => '123456789',
            'display_handle' => '+977 980-0000001',
            'business_account_id' => '987654321',
            'access_token' => 'EAAG-new-token',
        ])
        ->assertNotified('WhatsApp connected');

    $account = ChannelAccount::query()->where('external_id', '123456789')->sole();

    expect($account->status)->toBe(ChannelAccountStatus::Connected)
        ->and($account->access_token)->toBe('EAAG-new-token')
        ->and(DB::table('channel_accounts')->where('id', $account->id)->value('access_token'))->not->toBe('EAAG-new-token');
});

it('refuses to connect an account that another workspace already uses', function () {
    $other = $this->createWorkspace(name: 'Himalaya Exports');
    ChannelAccount::query()->create(['team_id' => $other->id, 'channel' => Channel::WhatsApp, 'external_id' => '123456789', 'name' => 'H', 'access_token' => 'x']);
    $this->actingInWorkspace($this->owner, $this->team);

    livewire(Channels::class)
        ->callAction('connectWhatsApp', ['name' => 'Mine', 'external_id' => '123456789', 'access_token' => 'tok'])
        ->assertNotified('WhatsApp not connected');
});

it('syncs approved WhatsApp templates', function () {
    $this->actingInWorkspace($this->owner, $this->team);
    Http::fake(['graph.facebook.com/*/444555666/message_templates*' => Http::response(['data' => [
        ['name' => 'follow_up', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, about {{2}}']]],
        ['name' => 'draft_one', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'PENDING', 'components' => []],
    ]])]);

    livewire(Channels::class)
        ->callTableAction('syncTemplates', $this->account)
        ->assertNotified('Templates synced');

    $template = WhatsAppTemplate::query()->sole();

    expect($template->name)->toBe('follow_up')
        ->and($template->parameter_count)->toBe(2);
});

it('flags expiring or revoked tokens every day and tells the people who manage channels', function () {
    Notification::fake();
    Http::fake(['graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => false, 'error' => ['message' => 'The user has not authorized application']]])]);

    $this->artisan('crm:check-channel-tokens')->assertSuccessful();

    expect($this->account->fresh()->status)->toBe(ChannelAccountStatus::ReconnectNeeded);
    Notification::assertSentTo($this->owner, ChannelNeedsReconnect::class);
    Notification::assertNotSentTo($this->rep, ChannelNeedsReconnect::class);
});

it('renders the inbox and channels pages', function () {
    $this->get('/app/acme-traders/inbox?conversation='.$this->conversation->id)->assertOk()->assertSee('Nabin KC');

    $this->actingAs($this->owner)->get('/app/acme-traders/channels')->assertOk()->assertSee('Acme Sales');
    $this->actingAs($this->rep)->get('/app/acme-traders/channels')->assertForbidden();
});

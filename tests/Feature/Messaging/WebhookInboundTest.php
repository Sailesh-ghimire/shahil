<?php

use App\Enums\ActivityType;
use App\Enums\Channel;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Enums\RecordSource;
use App\Integrations\Meta\Drivers\MessengerDriver;
use App\Integrations\Meta\GraphClient;
use App\Integrations\Meta\MetaApiException;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookLog;
use App\Notifications\NewConversationMessage;
use App\Support\Permissions\PermissionCatalog;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MetaPayloads;

beforeEach(function () {
    Http::preventStrayRequests();
    Notification::fake();

    $this->owner = User::factory()->create();
    $this->team = $this->createWorkspace($this->owner, 'Acme Traders');
    $this->rep = $this->memberOf($this->team, PermissionCatalog::SALES_REP);

    $this->whatsapp = ChannelAccount::query()->create([
        'team_id' => $this->team->id, 'channel' => Channel::WhatsApp, 'external_id' => '111222333',
        'name' => 'Acme Sales', 'access_token' => 'wa-token',
    ]);

    Filament::setTenant(null);
});

function postWebhook(string $channel, array $payload)
{
    return test()->postJson("/webhooks/meta/{$channel}", $payload, MetaPayloads::signedHeaders($payload));
}

it('answers Meta’s subscription check only with the right verify token', function () {
    $this->get('/webhooks/meta/whatsapp?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=12345')
        ->assertOk()
        ->assertSee('12345');

    $this->get('/webhooks/meta/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')->assertForbidden();
});

it('rejects payloads with a bad signature without storing them', function () {
    $payload = MetaPayloads::whatsAppText('111222333', '9779811234567', 'Hello');

    $this->postJson('/webhooks/meta/whatsapp', $payload, ['X-Hub-Signature-256' => 'sha256=forged'])->assertForbidden();
    $this->postJson('/webhooks/meta/whatsapp', $payload)->assertForbidden();

    expect(WebhookLog::query()->count())->toBe(0)
        ->and(Message::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('never downloads media from hosts outside Meta, and never sends the token to CDN hosts', function () {
    Http::fake(['*' => Http::response('bytes')]);
    $graph = app(GraphClient::class);

    expect(fn () => $graph->download('secret-token', 'https://attacker.example/steal'))->toThrow(MetaApiException::class)
        ->and(fn () => $graph->download('secret-token', 'http://scontent.xx.fbcdn.net/file.jpg'))->toThrow(MetaApiException::class)
        ->and(fn () => $graph->download('secret-token', 'https://fbcdn.net.attacker.example/x'))->toThrow(MetaApiException::class);

    $graph->download('secret-token', 'https://scontent.xx.fbcdn.net/file.jpg');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'fbcdn.net') && ! $request->hasHeader('Authorization'));
});

it('only downloads real attachments from Messenger, not shared links', function () {
    $events = app(MessengerDriver::class)->parseWebhook([
        'object' => 'page',
        'entry' => [['id' => '5550001', 'messaging' => [[
            'sender' => ['id' => 'PSID-9'], 'recipient' => ['id' => '5550001'], 'timestamp' => now()->getTimestampMs(),
            'message' => ['mid' => 'm_share', 'attachments' => [['type' => 'fallback', 'payload' => ['url' => 'https://attacker.example/x']]]],
        ]]]],
    ]);

    expect($events[0]->mediaUrl)->toBeNull()
        ->and($events[0]->hasMedia())->toBeFalse();
});

it('turns a WhatsApp message from a new sender into a lead and an assigned conversation', function () {
    postWebhook('whatsapp', MetaPayloads::whatsAppText('111222333', '9779811234567', 'Price for CRM for 5 users?'))->assertOk();

    $lead = Lead::query()->withoutGlobalScopes()->sole();
    $conversation = Conversation::query()->withoutGlobalScopes()->sole();
    $message = Message::query()->withoutGlobalScopes()->sole();

    expect($lead->team_id)->toBe($this->team->id)
        ->and($lead->name)->toBe('Nabin KC')
        ->and($lead->phone)->toBe('+9779811234567')
        ->and($lead->source)->toBe(RecordSource::WhatsApp)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->owner_id)->toBe($this->rep->id)
        ->and($conversation->lead_id)->toBe($lead->id)
        ->and($conversation->assignee_id)->toBe($this->rep->id)
        ->and($conversation->unread_count)->toBe(1)
        ->and($conversation->awaiting_reply_since)->not->toBeNull()
        ->and($message->body)->toBe('Price for CRM for 5 users?')
        ->and($message->status)->toBe(MessageStatus::Received)
        ->and($lead->activities()->withoutGlobalScopes()->first()->type)->toBe(ActivityType::WhatsApp)
        ->and(WebhookLog::query()->sole()->processed_at)->not->toBeNull();

    Notification::assertSentTo($this->rep, NewConversationMessage::class);
});

it('ignores Meta’s retries of the same message', function () {
    $payload = MetaPayloads::whatsAppText('111222333', '9779811234567', 'Hello', 'wamid.SAME');

    postWebhook('whatsapp', $payload)->assertOk();
    postWebhook('whatsapp', $payload)->assertOk();

    expect(Message::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Lead::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Conversation::query()->withoutGlobalScopes()->sole()->unread_count)->toBe(1);
});

it('links a known customer’s WhatsApp message to their contact', function () {
    $sita = Contact::factory()->forTeam($this->team)->create(['first_name' => 'Sita', 'owner_id' => $this->rep->id]);
    $sita->methods()->create(['type' => ContactMethodType::Phone, 'value' => '+977 980-1011111']);

    postWebhook('whatsapp', MetaPayloads::whatsAppText('111222333', '9779801011111', 'Can you send the proposal?'));

    $conversation = Conversation::query()->withoutGlobalScopes()->sole();

    expect($conversation->contact_id)->toBe($sita->id)
        ->and($conversation->assignee_id)->toBe($this->rep->id)
        ->and(Lead::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and($sita->activities()->withoutGlobalScopes()->sole()->body)->toBe('Can you send the proposal?');
});

it('routes each message to the workspace that owns the receiving number', function () {
    $other = $this->createWorkspace(name: 'Himalaya Exports');
    Filament::setTenant(null);
    ChannelAccount::query()->create(['team_id' => $other->id, 'channel' => Channel::WhatsApp, 'external_id' => '999888777', 'name' => 'Himalaya', 'access_token' => 'x']);

    postWebhook('whatsapp', MetaPayloads::whatsAppText('999888777', '4915112345678', 'Tea prices?', 'wamid.H1'));
    postWebhook('whatsapp', MetaPayloads::whatsAppText('000000000', '4915112345678', 'Unknown number', 'wamid.X1'));

    expect(Conversation::query()->withoutGlobalScopes()->sole()->team_id)->toBe($other->id)
        ->and(Lead::query()->withoutGlobalScopes()->sole()->team_id)->toBe($other->id)
        ->and(Message::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('moves delivery status forward and never backwards', function () {
    postWebhook('whatsapp', MetaPayloads::whatsAppText('111222333', '9779811234567', 'Hi'));
    $conversation = Conversation::query()->withoutGlobalScopes()->sole();
    $outbound = Message::query()->create([
        'team_id' => $this->team->id, 'conversation_id' => $conversation->id, 'direction' => Message::OUTBOUND,
        'external_id' => 'wamid.OUT1', 'body' => 'Hello!', 'status' => MessageStatus::Sent,
    ]);

    postWebhook('whatsapp', MetaPayloads::whatsAppStatus('111222333', 'wamid.OUT1', 'read'));
    postWebhook('whatsapp', MetaPayloads::whatsAppStatus('111222333', 'wamid.OUT1', 'delivered'));

    expect($outbound->fresh()->status)->toBe(MessageStatus::Read)
        ->and($outbound->fresh()->read_at)->not->toBeNull()
        ->and($outbound->fresh()->delivered_at)->not->toBeNull();
});

it('downloads WhatsApp media to our storage', function () {
    Storage::fake('local');
    Http::fake([
        'graph.facebook.com/*/MEDIA-1' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp/MEDIA-1', 'mime_type' => 'image/jpeg']),
        'lookaside.fbsbx.com/*' => Http::response('jpeg-bytes'),
    ]);

    postWebhook('whatsapp', MetaPayloads::whatsAppImage('111222333', '9779811234567', 'MEDIA-1'));

    $message = Message::query()->withoutGlobalScopes()->sole();

    expect($message->type)->toBe('image')
        ->and($message->body)->toBe('Our shop')
        ->and($message->media_path)->not->toBeNull();

    Storage::disk('local')->assertExists($message->media_path);

    $this->actingAs($this->rep)->get(route('messages.media', $message))->assertOk();

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('messages.media', $message))->assertNotFound();
});

it('receives Messenger and Instagram messages', function () {
    Http::fake([
        'graph.facebook.com/*/PSID-1*' => Http::response(['first_name' => 'Sunita', 'last_name' => 'Magar']),
        'graph.facebook.com/*/IGSID-1*' => Http::response(['name' => 'Dawa Sherpa', 'username' => 'dawa.treks']),
    ]);

    ChannelAccount::query()->create(['team_id' => $this->team->id, 'channel' => Channel::Facebook, 'external_id' => '5550001', 'name' => 'Acme Page', 'access_token' => 'fb']);
    ChannelAccount::query()->create(['team_id' => $this->team->id, 'channel' => Channel::Instagram, 'external_id' => '7770001', 'name' => '@acmetraders', 'access_token' => 'ig']);

    postWebhook('facebook', MetaPayloads::messaging('page', '5550001', 'PSID-1', 'Is your software good for homestays?', 'm_fb1'))->assertOk();
    postWebhook('instagram', MetaPayloads::messaging('instagram', '7770001', 'IGSID-1', 'Bulk price for 20 trackers?', 'm_ig1'))->assertOk();

    $leads = Lead::query()->withoutGlobalScopes()->orderBy('id')->get();

    expect($leads->pluck('name')->all())->toBe(['Sunita Magar', 'Dawa Sherpa'])
        ->and($leads->pluck('source')->all())->toBe([RecordSource::Facebook, RecordSource::Instagram])
        ->and(Message::query()->withoutGlobalScopes()->pluck('body')->all())->toBe(['Is your software good for homestays?', 'Bulk price for 20 trackers?']);
});

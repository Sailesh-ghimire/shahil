<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\Channel;
use App\Enums\CompanySize;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Enums\RecordSource;
use App\Enums\TaskType;
use App\Models\ChannelAccount;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Business records for Acme Traders, matching the panel preview.
 */
class AcmeDemoDataSeeder extends Seeder
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

        $this->seedCustomers($team, $users);
        $this->seedSales($team, $users);
        $this->seedTasks($team, $users);
        $this->seedInbox($team, $users);
    }

    /**
     * Demo channel accounts and chats from the preview. The tokens are placeholders, so replies fail
     * until a real account is connected in Settings → Channels.
     *
     * @param  Collection<string, User>  $users
     */
    protected function seedInbox(Team $team, Collection $users): void
    {
        $note = 'Demo account: reconnect with a real token to send messages.';
        $accounts = [
            'whatsapp' => ChannelAccount::query()->create(['team_id' => $team->id, 'channel' => Channel::WhatsApp, 'external_id' => '100000000000001', 'name' => 'Acme Sales', 'display_handle' => '+977 980-0000000', 'access_token' => 'demo-token', 'status_message' => $note]),
            'facebook' => ChannelAccount::query()->create(['team_id' => $team->id, 'channel' => Channel::Facebook, 'external_id' => '100000000000002', 'name' => 'Acme Traders (Page)', 'access_token' => 'demo-token', 'status_message' => $note]),
            'instagram' => ChannelAccount::query()->create(['team_id' => $team->id, 'channel' => Channel::Instagram, 'external_id' => '100000000000003', 'name' => '@acmetraders', 'access_token' => 'demo-token', 'status_message' => $note]),
        ];

        $lead = fn (string $name): ?Lead => Lead::query()->where('team_id', $team->id)->where('name', $name)->first();

        $chats = [
            ['whatsapp', '9779801011111', 'Sita Sharma', '+9779801011111', $this->contacts['sita'], null, 'meera', 0, [
                [false, 'Hi Meera, can you send the revised proposal with 80 units?', 48],
                [true, 'Sure Sita, I’ll share it by noon today.', 40],
                [false, 'Thanks. Board meets on the 12th.', 39],
            ]],
            ['instagram', 'IGSID-DAWA', 'Dawa Sherpa', '@dawa.treks', null, $lead('Dawa Sherpa'), 'meera', 2, [
                [false, 'Namaste! Do you offer bulk pricing for 20 GPS trackers?', 25],
                [false, 'For trekking guides, need them before the spring season.', 24],
            ]],
            ['facebook', 'PSID-SUNITA', 'Sunita Magar', 'Sunita Magar', null, $lead('Sunita Magar'), 'sanjay', 0, [
                [false, 'Is your booking software good for small homestays?', 1500],
                [true, 'Yes! Can I call you tomorrow to show a demo?', 1440],
            ]],
            ['whatsapp', '9779811234567', 'Nabin KC', '+9779811234567', null, $lead('Nabin KC'), null, 0, [
                [false, 'Price for CRM for 5 users?', 3 * 1440],
            ]],
            ['instagram', 'IGSID-BREWS', '@kathmandu.brews', '@kathmandu.brews', null, null, null, 1, [
                [false, 'Hey, do you do loyalty cards in the CRM?', 5],
            ]],
        ];

        foreach ($chats as [$channel, $threadId, $name, $handle, $contact, $chatLead, $assignee, $unread, $messages]) {
            $conversation = Conversation::query()->create([
                'team_id' => $team->id,
                'channel_account_id' => $accounts[$channel]->id,
                'external_thread_id' => $threadId,
                'display_name' => $name,
                'handle' => $handle,
                'contact_id' => $contact?->id,
                'lead_id' => $chatLead?->id,
                'assignee_id' => $assignee !== null ? $users[$assignee]->id : null,
                'unread_count' => $unread,
            ]);

            foreach ($messages as $index => [$isOurs, $body, $minutesAgo]) {
                $at = now()->subMinutes($minutesAgo);

                $message = $conversation->messages()->create([
                    'team_id' => $team->id,
                    'direction' => $isOurs ? Message::OUTBOUND : Message::INBOUND,
                    'external_id' => "demo-{$threadId}-{$index}",
                    'body' => $body,
                    'status' => $isOurs ? MessageStatus::Read : MessageStatus::Received,
                    'sent_by' => $isOurs ? $users[$assignee]->id : null,
                    'response_seconds' => $isOurs ? 480 : null,
                    'sent_at' => $at,
                ]);
                $message->forceFill(['created_at' => $at])->saveQuietly();

                $conversation->forceFill([
                    'last_message_at' => $at,
                    'last_message_preview' => $message->preview(),
                    'last_inbound_at' => $isOurs ? $conversation->last_inbound_at : $at,
                    'awaiting_reply_since' => $isOurs ? null : ($conversation->awaiting_reply_since ?? $at),
                ])->save();
            }
        }
    }

    /**
     * Follow-ups from the preview, due relative to today in the workspace timezone.
     *
     * @param  Collection<string, User>  $users
     */
    protected function seedTasks(Team $team, Collection $users): void
    {
        $deal = fn (string $title): Deal => Deal::query()->where('team_id', $team->id)->where('title', $title)->firstOrFail();

        $tasks = [
            ['Call Sita about the proposal', TaskType::Call, $this->contacts['sita'], 0, '10:00', 'meera', 15, false],
            ['Send revised quote to Lumbini Foods', TaskType::Email, $deal('Dashain stock system'), 0, '14:30', 'aarav', 60, false],
            ['Follow up with Anjali on app scope', TaskType::Call, $this->contacts['anjali'], -2, '11:00', 'meera', 0, false],
            ['Demo for Nimbus Software', TaskType::Meeting, $deal('ERP integration'), 2, '15:00', 'aarav', 1440, false],
            ['Renewal check-in with Ramesh', TaskType::Call, $this->contacts['ramesh'], 5, '11:30', 'sanjay', 15, false],
            ['Collect signed PO from Bagmati Textiles', TaskType::ToDo, $this->companies['bagmati'], -6, '12:00', 'sanjay', 0, true],
        ];

        foreach ($tasks as [$title, $type, $subject, $dayOffset, $time, $userKey, $remind, $done]) {
            [$hour, $minute] = explode(':', $time);

            $task = Task::query()->create([
                'team_id' => $team->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'title' => $title,
                'type' => $type,
                'due_at' => now($team->timezone)->addDays($dayOffset)->setTime((int) $hour, (int) $minute)->utc(),
                'remind_minutes_before' => $remind,
                'assignee_id' => $users[$userKey]->id,
                'created_by' => $users[$userKey]->id,
            ]);

            if ($done || $task->remind_at?->isPast()) {
                $task->forceFill(['reminded_at' => now(), 'completed_at' => $done ? now() : null])->save();
            }
        }
    }

    /**
     * @param  Collection<string, User>  $users
     */
    protected function seedSales(Team $team, Collection $users): void
    {
        $category = fn (string $name): int => ProductCategory::query()->firstOrCreate(['team_id' => $team->id, 'name' => $name])->id;

        $products = collect([
            'crm' => ['SW-CRM-01', 'CRM seat (annual)', 'Software', 12_000, true],
            'impl' => ['SV-IMP-01', 'Implementation package', 'Services', 150_000, true],
            'gps' => ['HW-GPS-02', 'GPS tracker unit', 'Hardware', 8_500, true],
            'support' => ['SV-SUP-12', 'Support plan (12 months)', 'Services', 60_000, true],
            'training' => ['SV-TRN-01', 'On-site training day', 'Services', 25_000, false],
        ])->map(fn (array $product): Product => Product::query()->create([
            'team_id' => $team->id,
            'sku' => $product[0],
            'name' => $product[1],
            'category_id' => $category($product[2]),
            'unit_price' => $product[3] * 100,
            'tax_rate' => 13,
            'is_active' => $product[4],
        ]));

        $pipeline = Pipeline::query()->where('team_id', $team->id)->firstOrFail();
        $stages = $pipeline->stages->keyBy('name');

        $deals = [
            ['Fleet tracking rollout', 'everest', 'sita', 'Proposal', 15, 'meera', [['gps', 80], ['impl', 1]], 0],
            ['Dashain stock system', 'lumbini', 'ramesh', 'Negotiation', 8, 'aarav', [['crm', 50], ['support', 10]], 1],
            ['Booking app and CRM', 'pokhara', 'anjali', 'Prospecting', 36, 'meera', [['impl', 2], ['training', 6]], 3],
            ['ERP integration', 'nimbus', 'priya', 'Proposal', 56, 'aarav', [['impl', 4], ['support', 3]], 0],
            ['Uniform supply 2026', 'bagmati', 'bikash', 'Won', -9, 'sanjay', [['crm', 20], ['impl', 2], ['support', 1]], 9],
            ['Cold-chain pilot', 'everest', 'sita', 'Lost', -23, 'meera', [['gps', 30], ['training', 2]], 20],
            ['Annual support renewal', 'lumbini', 'ramesh', 'Prospecting', 77, 'sanjay', [['support', 4]], 8],
        ];

        foreach ($deals as [$title, $companyKey, $contactKey, $stageName, $closeInDays, $ownerKey, $lines, $idleDays]) {
            $stage = $stages[$stageName];

            $deal = Deal::query()->create([
                'team_id' => $team->id,
                'title' => $title,
                'company_id' => $this->companies[$companyKey]->id,
                'contact_id' => $this->contacts[$contactKey]->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'owner_id' => $users[$ownerKey]->id,
                'expected_close_on' => now()->addDays($closeInDays),
                'closed_at' => $stage->isClosed() ? now()->addDays($closeInDays) : null,
                'lost_reason' => $stage->isLost() ? 'Price too high' : null,
                'stage_changed_at' => now()->subDays($idleDays),
            ]);

            foreach ($lines as $sort => [$productKey, $quantity]) {
                $product = $products[$productKey];

                $deal->lineItems()->create([
                    'team_id' => $team->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->unit_price,
                    'tax_rate' => $product->tax_rate,
                    'sort' => $sort,
                ]);
            }

            $deal->forceFill(['last_activity_at' => now()->subDays($idleDays)])->saveQuietly();
        }

        $leads = [
            ['Dawa Sherpa', 'Khumbu Trek Gear', RecordSource::Instagram, LeadStatus::New, 150_000, 'meera', null, null, '@dawa.treks', 1],
            ['Nabin KC', null, RecordSource::WhatsApp, LeadStatus::New, 0, 'meera', '+977 981-1234567', null, null, 3],
            ['Sunita Magar', 'Magar Homestay', RecordSource::Facebook, LeadStatus::New, 60_000, 'sanjay', null, null, null, 24],
            ['Hari Prasad Oli', 'Oli Hardware', RecordSource::Website, LeadStatus::Contacted, 220_000, 'aarav', '+977 984-5551234', 'hari@olihardware.example', null, 48],
            ['Manisha Tamang', 'Tamang Cafe Chain', RecordSource::WhatsApp, LeadStatus::Contacted, 310_000, 'meera', '+977 986-2223344', null, null, 96],
            ['Rojina Basnet', 'Hotel Yak View', RecordSource::Referral, LeadStatus::Qualified, 420_000, 'sanjay', '+977 985-7778899', 'rojina@yakview.example', null, 144],
            ['Test test', null, RecordSource::Facebook, LeadStatus::Unqualified, 0, 'meera', null, null, null, 192],
        ];

        foreach ($leads as $position => [$name, $company, $source, $status, $value, $ownerKey, $phone, $email, $handle, $hoursAgo]) {
            $lead = Lead::query()->create([
                'team_id' => $team->id,
                'name' => $name,
                'company_name' => $company,
                'source' => $source,
                'status' => $status,
                'estimated_value' => $value * 100,
                'owner_id' => $users[$ownerKey]->id,
                'phone' => $phone,
                'email' => $email,
                'handle' => $handle,
                'unqualified_reason' => $status === LeadStatus::Unqualified ? 'Spam' : null,
                'board_position' => $position,
            ]);

            $lead->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
        }
    }

    /**
     * @param  Collection<string, User>  $users
     */
    protected function seedCustomers(Team $team, Collection $users): void
    {
        $companies = [
            'everest' => ['Everest Logistics', 'Logistics', 'Kathmandu', 'https://everestlogistics.example', 'meera', CompanySize::Medium],
            'lumbini' => ['Lumbini Foods', 'FMCG', 'Butwal', 'https://lumbinifoods.example', 'aarav', CompanySize::Large],
            'pokhara' => ['Pokhara Adventure Co.', 'Travel', 'Pokhara', 'https://pokhara-adventure.example', 'meera', CompanySize::Small],
            'bagmati' => ['Bagmati Textiles', 'Manufacturing', 'Lalitpur', 'https://bagmatitex.example', 'sanjay', CompanySize::Large],
            'nimbus' => ['Nimbus Software', 'IT services', 'Kathmandu', 'https://nimbus.example', 'aarav', CompanySize::Small],
        ];

        foreach ($companies as $key => [$name, $industry, $city, $website, $owner, $size]) {
            $this->companies[$key] = Company::query()->create([
                'team_id' => $team->id,
                'name' => $name,
                'industry' => $industry,
                'address' => $city,
                'website' => $website,
                'owner_id' => $users[$owner]->id,
                'size' => $size,
            ]);
        }

        $tags = collect(['Decision maker', 'VIP', 'Technical'])
            ->mapWithKeys(fn (string $name): array => [$name => Tag::query()->create(['team_id' => $team->id, 'name' => $name])]);

        $people = [
            'sita' => ['Sita', 'Sharma', ['sita@everestlogistics.example', 'sita.sharma@mail.example'], ['+977 980-1011111'], [['everest', 'Operations manager'], ['nimbus', 'Advisor']], 'meera', ['Decision maker'], RecordSource::WhatsApp],
            'ramesh' => ['Ramesh', 'Thapa', ['ramesh@lumbinifoods.example'], ['+977 980-2022222', '+977 71-540000'], [['lumbini', 'Purchase head']], 'aarav', ['VIP'], RecordSource::Referral],
            'anjali' => ['Anjali', 'Gurung', ['anjali@pokhara-adventure.example'], ['+977 980-3033333'], [['pokhara', 'Founder']], 'meera', [], RecordSource::Instagram],
            'bikash' => ['Bikash', 'Rai', ['bikash@bagmatitex.example'], [], [['bagmati', 'Plant manager']], 'sanjay', ['Technical'], RecordSource::Import],
            'priya' => ['Priya', 'Joshi', ['priya@nimbus.example'], ['+977 980-5055555'], [['nimbus', 'CTO'], ['everest', 'Board member']], 'aarav', ['Decision maker'], RecordSource::Website],
            'kiran' => ['Kiran', 'Shrestha', [], ['+977 980-6066666'], [], 'meera', [], RecordSource::Facebook],
        ];

        foreach ($people as $key => [$first, $last, $emails, $phones, $jobs, $owner, $tagNames, $source]) {
            $contact = Contact::query()->create([
                'team_id' => $team->id,
                'first_name' => $first,
                'last_name' => $last,
                'owner_id' => $users[$owner]->id,
                'source' => $source,
            ]);

            foreach ($emails as $index => $email) {
                $contact->methods()->create(['team_id' => $team->id, 'type' => ContactMethodType::Email, 'value' => $email, 'is_primary' => $index === 0]);
            }

            foreach ($phones as $index => $phone) {
                $type = $source === RecordSource::WhatsApp && $index === 0 ? ContactMethodType::WhatsApp : ContactMethodType::Phone;
                $contact->methods()->create(['team_id' => $team->id, 'type' => $type, 'value' => $phone, 'is_primary' => $index === 0]);
            }

            foreach ($jobs as $index => [$companyKey, $title]) {
                $contact->companies()->attach($this->companies[$companyKey]->id, ['team_id' => $team->id, 'job_title' => $title, 'is_primary' => $index === 0]);
            }

            $contact->tags()->sync($tags->only($tagNames)->map->id->values()->all());

            $this->contacts[$key] = $contact;
        }

        $timeline = [
            ['sita', ActivityType::WhatsApp, 'Asked for the revised proposal with 80 GPS units.', 'meera', now()->subHours(2)],
            ['sita', ActivityType::Meeting, 'Site visit at the Sinamangal depot. Fleet of 80 trucks, current tracker contract ends in December.', 'meera', now()->subDays(6)],
            ['sita', ActivityType::Call, 'Intro call. Interested in fleet tracking; needs board approval above Rs 10 lakh.', 'meera', now()->subDays(12)],
            ['ramesh', ActivityType::Call, 'Confirmed Dashain stock order quantities. Wants support plan bundled.', 'aarav', now()->subDay()],
            ['priya', ActivityType::Email, 'Sent ERP integration scope document.', 'aarav', now()->subHours(3)],
            ['anjali', ActivityType::Note, 'Prefers Instagram DMs over calls.', 'meera', now()->subDays(3)],
            ['bikash', ActivityType::Call, 'Uniform order delivered. Asked about next year.', 'sanjay', now()->subDays(9)],
        ];

        foreach ($timeline as [$contactKey, $type, $body, $userKey, $when]) {
            $this->contacts[$contactKey]->logActivity($type, $body, $users[$userKey], $when);
        }
    }
}

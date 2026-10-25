<?php

namespace App\Actions\Messaging;

use App\Actions\Sales\AssignLeadOwner;
use App\Enums\ContactMethodType;
use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Integrations\Meta\ChannelDrivers;
use App\Integrations\Meta\Data\InboundMessage;
use App\Jobs\DownloadMessageMedia;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Notifications\NewConversationMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves a customer's message: finds or opens the conversation, links it to a contact (matched by phone or
 * handle) or creates a lead, writes the timeline entry and tells the assignee. Retries are skipped by message id.
 * Must run inside the account's workspace.
 */
class ReceiveInboundMessage
{
    public function __construct(
        protected ChannelDrivers $drivers,
        protected AssignLeadOwner $assignLeadOwner,
    ) {}

    public function handle(ChannelAccount $account, InboundMessage $inbound): ?Message
    {
        if (Message::query()->withoutGlobalScope('workspace')->where('external_id', $inbound->externalMessageId)->exists()) {
            return null;
        }

        try {
            [$message, $isNewSender] = DB::transaction(fn (): array => $this->store($account, $inbound));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $conversation = $message->conversation;

        $conversation->timelineSubject()?->logActivity(
            type: $account->channel->activityType(),
            body: $message->preview(),
            happenedAt: $inbound->sentAt,
            title: 'Message from '.$conversation->title(),
        );

        if ($inbound->hasMedia()) {
            DownloadMessageMedia::dispatch($message->getKey());
        }

        if ($isNewSender || $conversation->assignee_id !== null) {
            NewConversationMessage::sendFor($conversation, $message, $isNewSender);
        }

        return $message;
    }

    /**
     * @return array{0: Message, 1: bool}
     */
    protected function store(ChannelAccount $account, InboundMessage $inbound): array
    {
        $conversation = Conversation::query()
            ->where('channel_account_id', $account->getKey())
            ->where('external_thread_id', $inbound->threadId)
            ->lockForUpdate()
            ->first();

        $isNewSender = $conversation === null;

        if ($conversation === null) {
            $conversation = $this->openConversation($account, $inbound);
        }

        $message = $conversation->messages()->create([
            'team_id' => $account->team_id,
            'direction' => Message::INBOUND,
            'external_id' => $inbound->externalMessageId,
            'type' => $inbound->type,
            'body' => $inbound->body,
            'media_external_id' => $inbound->mediaId,
            'media_url' => $inbound->mediaUrl,
            'media_mime' => $inbound->mediaMime,
            'status' => MessageStatus::Received,
            'sent_at' => $inbound->sentAt,
        ]);

        $conversation->forceFill([
            'last_message_at' => $inbound->sentAt,
            'last_message_preview' => $message->preview(),
            'last_inbound_at' => $inbound->sentAt,
            'awaiting_reply_since' => $conversation->awaiting_reply_since ?? $inbound->sentAt,
            'unread_count' => $conversation->unread_count + 1,
        ])->save();

        return [$message->setRelation('conversation', $conversation), $isNewSender];
    }

    protected function openConversation(ChannelAccount $account, InboundMessage $inbound): Conversation
    {
        $name = $inbound->senderName ?? $this->drivers->for($account->channel)->fetchSenderName($account, $inbound->threadId);
        $contact = $this->matchContact($account, $inbound);
        $lead = null;

        if ($contact === null) {
            $lead = $this->matchOpenLead($account, $inbound) ?? $this->createLead($account, $inbound, $name);
        }

        return Conversation::query()->create([
            'team_id' => $account->team_id,
            'channel_account_id' => $account->getKey(),
            'external_thread_id' => $inbound->threadId,
            'display_name' => $name,
            'handle' => $inbound->senderPhone ?? $name,
            'contact_id' => $contact?->getKey(),
            'lead_id' => $lead?->getKey(),
            'assignee_id' => $contact->owner_id ?? $lead?->owner_id,
        ]);
    }

    /**
     * WhatsApp senders match on any phone/WhatsApp number; Messenger and Instagram on a stored scoped id.
     */
    protected function matchContact(ChannelAccount $account, InboundMessage $inbound): ?Contact
    {
        if ($inbound->senderPhone !== null) {
            return Contact::query()->withMethod($inbound->senderPhone, ContactMethodType::WhatsApp)->first()
                ?? Contact::query()->withMethod($inbound->senderPhone, ContactMethodType::Phone)->first();
        }

        $previous = Conversation::query()
            ->where('external_thread_id', $inbound->threadId)
            ->whereNotNull('contact_id')
            ->value('contact_id');

        return $previous === null ? null : Contact::query()->find($previous);
    }

    protected function matchOpenLead(ChannelAccount $account, InboundMessage $inbound): ?Lead
    {
        if ($inbound->senderPhone === null) {
            return null;
        }

        return Lead::query()
            ->where('phone', $inbound->senderPhone)
            ->whereNotIn('status', [LeadStatus::Converted, LeadStatus::Unqualified])
            ->first();
    }

    protected function createLead(ChannelAccount $account, InboundMessage $inbound, ?string $name): Lead
    {
        $lead = Lead::query()->create([
            'team_id' => $account->team_id,
            'name' => $name ?? $inbound->senderPhone ?? 'New '.$account->channel->getLabel().' contact',
            'phone' => $inbound->senderPhone,
            'handle' => $inbound->senderPhone === null ? $name : null,
            'source' => $account->channel->recordSource(),
            'status' => LeadStatus::New,
            'notes' => $inbound->body !== null ? 'First message: '.str($inbound->body)->limit(500) : null,
        ]);

        $this->assignLeadOwner->handle($lead);

        return $lead->refresh();
    }
}

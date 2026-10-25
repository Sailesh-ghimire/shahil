<x-filament-panels::page>
    @php
        $active = $this->getActive();
        $conversations = $this->getConversations();
    @endphp

    <div class="crm-inbox {{ $active ? 'has-active' : '' }}" wire:poll.10s>
        {{-- Conversation list --}}
        <aside class="crm-inbox-list">
            <div class="crm-inbox-filters">
                <input type="search" placeholder="Search chats" wire:model.live.debounce.400ms="search" aria-label="Search chats">
                <div class="crm-chips">
                    @foreach (['all' => 'All', 'mine' => 'Assigned to me', 'unassigned' => 'Unassigned', 'unread' => 'Unread'] as $value => $label)
                        <button type="button" class="crm-chip {{ $this->show === $value ? 'on' : '' }}" wire:click="$set('show', '{{ $value }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="crm-chips">
                    <button type="button" class="crm-chip {{ $this->channel === null ? 'on' : '' }}" wire:click="$set('channel', null)">All channels</button>
                    @foreach (\App\Enums\Channel::cases() as $channelOption)
                        <button type="button" class="crm-chip {{ $this->channel === $channelOption->value ? 'on' : '' }}" wire:click="$set('channel', '{{ $channelOption->value }}')">{{ $channelOption->getLabel() }}</button>
                    @endforeach
                </div>
            </div>

            <div class="crm-inbox-items">
                @forelse ($conversations as $item)
                    <button type="button" wire:key="conv-{{ $item->id }}" class="crm-inbox-item {{ $active?->id === $item->id ? 'on' : '' }}" wire:click="open({{ $item->id }})">
                        <span class="crm-row">
                            <span class="crm-ch {{ $item->channelAccount->channel->value }}">{{ $item->channelAccount->channel->getLabel() }}</span>
                            <strong>{{ $item->title() }}</strong>
                            <small>{{ $item->last_message_at?->shortRelativeDiffForHumans() }}</small>
                        </span>
                        <span class="crm-row">
                            <span class="crm-preview">{{ $item->last_message_preview }}</span>
                            @if ($item->unread_count > 0)
                                <span class="crm-unread">{{ $item->unread_count }}</span>
                            @endif
                        </span>
                    </button>
                @empty
                    <p class="crm-empty" style="padding:2rem 1rem">No conversations. Messages from connected channels appear here.</p>
                @endforelse
            </div>
        </aside>

        {{-- Thread --}}
        <section class="crm-inbox-thread">
            @if ($active)
                <header class="crm-thread-head">
                    <button type="button" class="crm-back" wire:click="closeConversation" aria-label="Back to conversations">←</button>
                    <span class="crm-ch {{ $active->channelAccount->channel->value }}">{{ $active->channelAccount->channel->getLabel() }}</span>
                    <strong>{{ $active->title() }}</strong>
                    <span class="crm-sub">{{ $active->handle }}</span>
                    <span style="margin-left:auto;display:flex;gap:.5rem;flex-wrap:wrap">
                        {{ $this->assignToMeAction }}
                        {{ $this->assignAction }}
                    </span>
                </header>

                <div class="crm-thread-messages" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$el.scrollTop = $el.scrollHeight">
                    @foreach ($this->getThreadMessages($active) as $message)
                        <div wire:key="msg-{{ $message->id }}" class="crm-msg {{ $message->isInbound() ? '' : 'us' }} {{ $message->status->value === 'failed' ? 'failed' : '' }}">
                            @if ($message->template_name)
                                <div class="crm-msg-tag">Template: {{ $message->template_name }}</div>
                            @endif
                            @if ($message->media_path)
                                <a href="{{ route('messages.media', $message) }}" target="_blank" rel="noopener">
                                    {{ str_starts_with((string) $message->media_mime, 'image/') ? 'View image' : 'Download '.$message->type }}
                                </a>
                            @elseif ($message->type !== 'text' && $message->type !== 'template')
                                <div class="crm-msg-tag">[{{ ucfirst($message->type) }}]</div>
                            @endif
                            @if (filled($message->body))
                                <div style="white-space:pre-wrap">{{ $message->body }}</div>
                            @endif
                            <small>
                                {{ $message->created_at->timezone(auth()->user()->timezone)->format('j M, H:i') }}
                                @unless ($message->isInbound())
                                    · {{ $message->sender?->name }} · {{ $message->status->getLabel() }}
                                    @if ($message->error) — {{ $message->error }} @endif
                                @endunless
                            </small>
                        </div>
                    @endforeach
                </div>

                <footer class="crm-composer">
                    @php($canReply = auth()->user()->can('reply', $active))
                    @if (! $active->channelAccount->isUsable())
                        <div class="crm-warn">This channel needs to be reconnected before you can reply (Settings → Channels).</div>
                    @elseif (! $canReply)
                        <div class="crm-warn">Only the assigned person can reply to this conversation.</div>
                    @elseif ($active->channelAccount->channel->value === 'whatsapp' && ! $active->isWithinReplyWindow())
                        <div class="crm-warn">More than 24 hours since the customer’s last message: WhatsApp only allows approved templates now (charged by Meta).</div>
                        {{ $this->sendTemplateAction }}
                    @else
                        <form wire:submit="send" class="crm-composer-row">
                            <textarea wire:model="reply" rows="2" placeholder="Write a reply…" aria-label="Reply" maxlength="4096"
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">Send</x-filament::button>
                        </form>
                        @if ($active->channelAccount->channel->value === 'whatsapp')
                            <div style="margin-top:.5rem">{{ $this->sendTemplateAction }}</div>
                        @endif
                    @endif
                </footer>
            @else
                <div class="crm-empty" style="margin:auto">Choose a conversation.</div>
            @endif
        </section>

        {{-- Details --}}
        <aside class="crm-inbox-side">
            @if ($active)
                <h3 style="font-weight:600;margin-bottom:.5rem">Linked to</h3>
                @if ($active->contact)
                    <p>Contact: <a style="color:var(--primary-600);font-weight:600" href="{{ \App\Filament\Resources\Contacts\ContactResource::getUrl('view', ['record' => $active->contact]) }}">{{ $active->contact->name }}</a></p>
                @elseif ($active->lead)
                    <p>Lead: <a style="color:var(--primary-600);font-weight:600" href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('view', ['record' => $active->lead]) }}">{{ $active->lead->name }}</a>
                        <span class="crm-sub">({{ $active->lead->status->getLabel() }})</span></p>
                @else
                    <p class="crm-sub">Not linked to a contact or lead.</p>
                @endif
                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin:.75rem 0">
                    {{ $this->createLeadAction }}
                    {{ $this->linkContactAction }}
                </div>
                <dl style="display:grid;gap:.5rem;font-size:.875rem">
                    <div><dt class="crm-sub">Assigned to</dt><dd>{{ $active->assignee?->name ?? 'Nobody' }}</dd></div>
                    <div><dt class="crm-sub">Account</dt><dd>{{ $active->channelAccount->name }}</dd></div>
                    <div><dt class="crm-sub">Reply window</dt><dd>{{ $active->isWithinReplyWindow() ? 'Open until '.$active->last_inbound_at->copy()->addDay()->timezone(auth()->user()->timezone)->format('j M, H:i') : 'Closed' }}</dd></div>
                </dl>
            @endif
        </aside>
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div class="crm-board-toolbar">
        <label style="display:inline-flex;gap:.5rem;align-items:center;font-size:.875rem">
            <input type="checkbox" wire:model.live="onlyMine">
            Only my leads
        </label>
    </div>

    <div class="crm-board">
        @foreach ($this->getColumns() as $statusValue => $column)
            <section class="crm-col" wire:key="lead-col-{{ $statusValue }}">
                <header>
                    <x-filament::badge :color="$column['status']->getColor()">{{ $column['status']->getLabel() }}</x-filament::badge>
                    <span class="crm-count">{{ $column['total'] }}</span>
                </header>
                <div class="crm-sum">{{ $column['value'] }}</div>

                <ul wire:sort="moveLead" wire:sort:group="leads" wire:sort:group-id="{{ $statusValue }}">
                    @forelse ($column['leads'] as $lead)
                        <li class="crm-card" wire:key="lead-{{ $lead->id }}" wire:sort:item="{{ $lead->id }}">
                            <a class="crm-title" href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('view', ['record' => $lead]) }}" wire:sort:ignore>{{ $lead->name }}</a>
                            @if ($lead->company_name)
                                <div class="crm-sub">{{ $lead->company_name }}</div>
                            @endif
                            <div class="crm-meta">
                                <span class="crm-badge {{ $lead->source->getColor() }}">{{ $lead->source->getLabel() }}</span>
                                @if ($lead->estimated_value > 0)
                                    <span>{{ \App\Support\Money::short($lead->estimated_value, currentTeam()->currency) }}</span>
                                @endif
                                <span>{{ $lead->created_at->shortAbsoluteDiffForHumans() }}</span>
                                <span class="crm-who">
                                    <span class="crm-initials" title="{{ $lead->owner?->name ?? 'Unassigned' }}">{{ $lead->owner?->initials() ?? '–' }}</span>
                                </span>
                            </div>
                            <select class="crm-move" wire:sort:ignore aria-label="Move {{ $lead->name }}"
                                wire:change="moveLead({{ $lead->id }}, 0, $event.target.value)">
                                @foreach (\App\Enums\LeadStatus::boardColumns() as $option)
                                    <option value="{{ $option->value }}" @selected($option === $lead->status)>{{ $option->getLabel() }}</option>
                                @endforeach
                            </select>
                        </li>
                    @empty
                        <li class="crm-empty" wire:sort:ignore>No leads</li>
                    @endforelse
                </ul>

                @if ($column['total'] > $column['leads']->count())
                    <button type="button" class="crm-more" wire:click="loadMore('{{ $statusValue }}')">
                        Load more ({{ $column['total'] - $column['leads']->count() }})
                    </button>
                @endif
            </section>
        @endforeach
    </div>
</x-filament-panels::page>

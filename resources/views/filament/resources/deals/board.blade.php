<x-filament-panels::page>
    @php($currency = currentTeam()->currency)
    @php($columns = $this->getColumns())

    <div class="crm-board-toolbar">
        @if (count($this->getPipelineOptions()) > 1)
            <label style="display:inline-flex;gap:.5rem;align-items:center;font-size:.875rem">
                Pipeline
                <select wire:model.live="pipelineId">
                    @foreach ($this->getPipelineOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label style="display:inline-flex;gap:.5rem;align-items:center;font-size:.875rem">
            <input type="checkbox" wire:model.live="onlyMine">
            Only my deals
        </label>
    </div>

    <div class="crm-board">
        @foreach ($columns as $stageId => $column)
            <section class="crm-col" wire:key="deal-col-{{ $stageId }}">
                <header>
                    <span>{{ $column['stage']->name }}</span>
                    @if ($column['stage']->isClosed())
                        <x-filament::badge size="sm" :color="$column['stage']->kind->getColor()">{{ $column['stage']->kind->getLabel() }}</x-filament::badge>
                    @endif
                    <span class="crm-count">{{ $column['total'] }}</span>
                </header>
                <div class="crm-sum">{{ $column['value'] }} · {{ $column['stage']->probability }}%</div>

                <ul wire:sort="moveDeal" wire:sort:group="deals" wire:sort:group-id="{{ $stageId }}">
                    @forelse ($column['deals'] as $deal)
                        <li class="crm-card" wire:key="deal-{{ $deal->id }}" wire:sort:item="{{ $deal->id }}">
                            <a class="crm-title" href="{{ \App\Filament\Resources\Deals\DealResource::getUrl('view', ['record' => $deal]) }}" wire:sort:ignore>{{ $deal->title }}</a>
                            @if ($deal->company)
                                <div class="crm-sub">{{ $deal->company->name }}</div>
                            @endif
                            <div class="crm-meta">
                                <strong style="color:inherit">{{ \App\Support\Money::format($deal->amount, $currency) }}</strong>
                                @if ($deal->expected_close_on && ! $deal->closed_at)
                                    <span>· {{ $deal->expected_close_on->format('j M') }}</span>
                                @endif
                                @if ($deal->isStale())
                                    <span class="crm-stale" title="No activity for {{ $deal->daysIdle() }} days">{{ $deal->daysIdle() }}d idle</span>
                                @endif
                                <span class="crm-who">
                                    <span class="crm-initials" title="{{ $deal->owner?->name ?? 'Unassigned' }}">{{ $deal->owner?->initials() ?? '–' }}</span>
                                </span>
                            </div>
                            <select class="crm-move" wire:sort:ignore aria-label="Move {{ $deal->title }}"
                                wire:change="moveDeal({{ $deal->id }}, 0, $event.target.value)">
                                @foreach ($columns as $optionId => $option)
                                    <option value="{{ $optionId }}" @selected($optionId === $deal->stage_id)>{{ $option['stage']->name }}</option>
                                @endforeach
                            </select>
                        </li>
                    @empty
                        <li class="crm-empty" wire:sort:ignore>No deals</li>
                    @endforelse
                </ul>

                @if ($column['total'] > $column['deals']->count())
                    <button type="button" class="crm-more" wire:click="loadMore({{ $stageId }})">
                        Load more ({{ $column['total'] - $column['deals']->count() }})
                    </button>
                @endif
            </section>
        @endforeach
    </div>
</x-filament-panels::page>

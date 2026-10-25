<x-filament-widgets::widget>
    <x-filament::section heading="Lead-to-won funnel" description="Where do leads drop out?">
        <div style="display:grid;gap:.5rem">
            @foreach ($this->getSteps() as $step)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:.8125rem;margin-bottom:.25rem">
                        <span style="font-weight:600">{{ $step['label'] }}</span>
                        <span style="color:var(--gray-500)">
                            {{ number_format($step['count']) }}
                            @if ($step['drop'] !== null && $step['drop'] > 0)
                                · <span style="color:var(--danger-600)">−{{ $step['drop'] }}%</span>
                            @endif
                        </span>
                    </div>
                    <div style="background:var(--gray-100);border-radius:.375rem;height:.75rem;overflow:hidden" class="crm-funnel-track">
                        <div style="width:{{ max(2, $step['percent']) }}%;height:100%;background:var(--primary-500);border-radius:.375rem"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

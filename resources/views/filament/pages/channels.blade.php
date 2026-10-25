<x-filament-panels::page>
    {{ $this->table }}

    <x-filament::section
        heading="Webhook setup"
        description="In your Meta app, set these callback URLs (Webhooks product) and subscribe to the messages fields."
        collapsible
        :collapsed="$this->isMetaConfigured()"
    >
        @unless ($this->isMetaConfigured())
            <p style="color:var(--danger-600);font-size:.875rem;margin-bottom:.75rem">
                META_APP_SECRET and META_WEBHOOK_VERIFY_TOKEN are not set on the server, so incoming messages will be rejected.
            </p>
        @endunless

        <dl style="display:grid;gap:.5rem;font-size:.875rem">
            @foreach ($this->getWebhookUrls() as $label => $url)
                <div>
                    <dt style="font-weight:600">{{ $label }}</dt>
                    <dd style="font-family:ui-monospace,monospace;color:var(--gray-600);word-break:break-all">{{ $url }}</dd>
                </div>
            @endforeach
            <div>
                <dt style="font-weight:600">Verify token</dt>
                <dd style="color:var(--gray-600)">The value of META_WEBHOOK_VERIFY_TOKEN in the server’s .env file.</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>

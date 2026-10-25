<?php

namespace App\Actions\Messaging;

use App\Enums\Channel;
use App\Enums\ChannelAccountStatus;
use App\Integrations\Meta\ChannelDrivers;
use App\Models\ChannelAccount;
use App\Models\Team;
use App\Models\User;
use InvalidArgumentException;

/**
 * Connects (or reconnects) a WhatsApp number, Page or Instagram account to a workspace and checks the token works.
 * An account can belong to one workspace only, because webhooks are routed by its id.
 */
class ConnectChannelAccount
{
    public function __construct(protected ChannelDrivers $drivers) {}

    /**
     * @param  array{name: string, external_id: string, access_token: string, business_account_id?: ?string, display_handle?: ?string}  $data
     */
    public function handle(Team $team, User $user, Channel $channel, array $data): ChannelAccount
    {
        $externalId = trim($data['external_id']);

        $existing = ChannelAccount::query()
            ->withoutGlobalScope('workspace')
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->team_id !== $team->getKey() && $existing->status !== ChannelAccountStatus::Disconnected) {
            throw new InvalidArgumentException('This account is already connected to another workspace.');
        }

        $account = $existing ?? new ChannelAccount(['channel' => $channel, 'external_id' => $externalId]);
        $account->fill([
            'team_id' => $team->getKey(),
            'name' => $data['name'],
            'display_handle' => $data['display_handle'] ?? null,
            'business_account_id' => $data['business_account_id'] ?? null,
            'access_token' => trim($data['access_token']),
            'connected_by' => $user->getKey(),
        ]);

        $status = $this->drivers->for($channel)->checkToken($account);

        if (! $status->isValid) {
            throw new InvalidArgumentException('Meta rejected the access token: '.($status->message ?? 'invalid token').'.');
        }

        $account->fill([
            'status' => ChannelAccountStatus::Connected,
            'status_message' => null,
            'token_expires_at' => $status->expiresAt,
            'last_checked_at' => now(),
        ])->save();

        return $account;
    }

    public function disconnect(ChannelAccount $account): void
    {
        $account->forceFill([
            'status' => ChannelAccountStatus::Disconnected,
            'status_message' => 'Disconnected by '.(auth()->user()->name ?? 'system').'.',
        ])->save();
    }
}

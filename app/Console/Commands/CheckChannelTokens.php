<?php

namespace App\Console\Commands;

use App\Enums\ChannelAccountStatus;
use App\Integrations\Meta\ChannelDrivers;
use App\Models\ChannelAccount;
use App\Models\Team;
use App\Models\User;
use App\Notifications\ChannelNeedsReconnect;
use App\Support\Workspace\CurrentWorkspace;
use Illuminate\Console\Command;

/**
 * Daily: checks every connected account's token and flags "Reconnect needed" (tokens expiring within 7 days too).
 */
class CheckChannelTokens extends Command
{
    protected $signature = 'crm:check-channel-tokens';

    protected $description = 'Check Meta access tokens and flag accounts that need reconnecting';

    public function handle(ChannelDrivers $drivers, CurrentWorkspace $workspace): int
    {
        $flagged = 0;

        ChannelAccount::query()
            ->withoutGlobalScope('workspace')
            ->where('status', ChannelAccountStatus::Connected)
            ->each(function (ChannelAccount $account) use ($drivers, $workspace, &$flagged): void {
                $status = $drivers->for($account->channel)->checkToken($account);
                $expiringSoon = $status->expiresAt !== null && $status->expiresAt->lessThan(now()->addDays(7));

                $account->forceFill([
                    'last_checked_at' => now(),
                    'token_expires_at' => $status->expiresAt,
                ]);

                if ($status->isValid && ! $expiringSoon) {
                    $account->save();

                    return;
                }

                $account->forceFill([
                    'status' => ChannelAccountStatus::ReconnectNeeded,
                    'status_message' => $status->isValid ? 'Access expires '.$status->expiresAt->toFormattedDayDateString().'.' : $status->message,
                ])->save();

                $flagged++;

                $team = Team::query()->findOrFail($account->team_id);

                $workspace->run($team, fn () => $team->members()->get()
                    ->filter(function (User $user): bool {
                        $user->forgetCachedPermissions();

                        return $user->hasPermissionTo('channels.manage');
                    })
                    ->each(fn (User $user) => $user->notify(new ChannelNeedsReconnect($account))));
            });

        $this->components->info("{$flagged} account(s) need reconnecting.");

        return self::SUCCESS;
    }
}

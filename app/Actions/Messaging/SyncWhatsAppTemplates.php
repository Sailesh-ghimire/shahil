<?php

namespace App\Actions\Messaging;

use App\Enums\Channel;
use App\Integrations\Meta\Drivers\WhatsAppDriver;
use App\Models\ChannelAccount;
use App\Models\WhatsAppTemplate;
use InvalidArgumentException;

/**
 * Pulls approved templates for a WhatsApp number from Meta. Only APPROVED templates can be sent.
 */
class SyncWhatsAppTemplates
{
    public function __construct(protected WhatsAppDriver $driver) {}

    public function handle(ChannelAccount $account): int
    {
        if ($account->channel !== Channel::WhatsApp) {
            throw new InvalidArgumentException('Templates are only available on WhatsApp.');
        }

        $templates = collect($this->driver->fetchTemplates($account))
            ->filter(fn (array $template): bool => $template['status'] === 'APPROVED');

        $keep = [];

        foreach ($templates as $template) {
            preg_match_all('/\{\{(\d+)\}\}/', (string) $template['body'], $matches);

            $model = WhatsAppTemplate::query()->updateOrCreate(
                ['channel_account_id' => $account->getKey(), 'name' => $template['name'], 'language' => $template['language']],
                [
                    'team_id' => $account->team_id,
                    'category' => $template['category'],
                    'status' => $template['status'],
                    'body' => $template['body'],
                    'parameter_count' => $matches[1] === [] ? 0 : max(array_map('intval', $matches[1])),
                ],
            );

            $keep[] = $model->getKey();
        }

        WhatsAppTemplate::query()->where('channel_account_id', $account->getKey())->whereNotIn('id', $keep)->delete();

        return count($keep);
    }
}

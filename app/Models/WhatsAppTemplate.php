<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved WhatsApp message template, synced from Meta. Templates are the only way to
 * message a customer more than 24 hours after their last message (Meta charges for them).
 *
 * @property int $id
 * @property int $team_id
 * @property int $channel_account_id
 * @property string $name
 * @property string $language
 * @property ?string $category
 * @property string $status
 * @property ?string $body
 * @property int $parameter_count
 */
class WhatsAppTemplate extends Model
{
    use BelongsToWorkspace;

    protected $table = 'whatsapp_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'channel_account_id',
        'name',
        'language',
        'category',
        'status',
        'body',
        'parameter_count',
    ];

    /**
     * @return BelongsTo<ChannelAccount, $this>
     */
    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    /**
     * The body with {{1}}, {{2}}… replaced by the given values.
     *
     * @param  list<string>  $parameters
     */
    public function render(array $parameters): string
    {
        $body = (string) $this->body;

        foreach ($parameters as $index => $value) {
            $body = str_replace('{{'.($index + 1).'}}', $value, $body);
        }

        return $body;
    }
}

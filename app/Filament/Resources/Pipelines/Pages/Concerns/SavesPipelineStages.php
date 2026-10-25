<?php

namespace App\Filament\Resources\Pipelines\Pages\Concerns;

use App\Models\Pipeline;
use App\Models\Stage;
use Filament\Notifications\Notification;

/**
 * Keeps one default pipeline per workspace and refuses to drop stages that still hold deals.
 */
trait SavesPipelineStages
{
    protected function ensureRemovedStagesAreEmpty(?Pipeline $pipeline): void
    {
        if ($pipeline === null) {
            return;
        }

        /** @var array<string, array<string, mixed>> $stages */
        $stages = $this->data['stages'] ?? [];

        $keptIds = collect($stages)
            ->keys()
            ->map(fn (string $key): ?int => str_starts_with($key, 'record-') ? (int) substr($key, 7) : null)
            ->filter()
            ->all();

        $blocked = $pipeline->stages()
            ->whereNotIn('id', $keptIds)
            ->whereHas('deals', fn ($query) => $query->withTrashed())
            ->pluck('name');

        if ($blocked->isNotEmpty()) {
            Notification::make()
                ->title('Stage still has deals')
                ->body('Move the deals out of '.e($blocked->implode(', ')).' before removing it.')
                ->warning()
                ->send();

            $this->halt();
        }
    }

    protected function keepSingleDefault(Pipeline $pipeline): void
    {
        if ($pipeline->is_default) {
            Pipeline::query()->whereKeyNot($pipeline->getKey())->update(['is_default' => false]);

            return;
        }

        if (! Pipeline::query()->where('is_default', true)->exists()) {
            $pipeline->forceFill(['is_default' => true])->save();
        }

        Stage::query()->where('pipeline_id', $pipeline->getKey())->whereNull('team_id')->update(['team_id' => $pipeline->team_id]);
    }
}

<?php

namespace App\Actions\Sales;

use App\Enums\ActivityType;
use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a deal to another stage. Won needs a close date; Lost needs a reason.
 * Every move writes a timeline entry (and an audit entry, through the model).
 */
class MoveDealToStage
{
    public function handle(Deal $deal, Stage $stage, ?Carbon $closedAt = null, ?string $lostReason = null, ?int $position = null): Deal
    {
        if ($stage->pipeline_id !== $deal->pipeline_id) {
            throw new InvalidArgumentException('That stage belongs to another pipeline.');
        }

        if ($stage->isLost() && blank($lostReason)) {
            throw new InvalidArgumentException('Give a reason when marking a deal as lost.');
        }

        return DB::transaction(function () use ($deal, $stage, $closedAt, $lostReason, $position): Deal {
            $fromStage = $deal->stage;
            $changed = $fromStage->getKey() !== $stage->getKey();

            $deal->fill([
                'stage_id' => $stage->getKey(),
                'closed_at' => $stage->isClosed() ? ($closedAt ?? $deal->closed_at ?? now()) : null,
                'lost_reason' => $stage->isLost() ? $lostReason : null,
            ]);

            if ($changed) {
                $deal->stage_changed_at = now();
            }

            if ($position !== null) {
                $this->reorder($deal, $stage, $position);
            }

            $deal->save();

            if ($changed) {
                $deal->logActivity(
                    type: ActivityType::StageChange,
                    body: $stage->isLost() ? "Reason: {$lostReason}" : null,
                    title: "{$fromStage->name} → {$stage->name}",
                );
            }

            return $deal->setRelation('stage', $stage);
        });
    }

    /**
     * Puts the deal at $position (zero-based) in the column and renumbers the others.
     */
    protected function reorder(Deal $deal, Stage $stage, int $position): void
    {
        $ids = Deal::query()
            ->where('stage_id', $stage->getKey())
            ->whereKeyNot($deal->getKey())
            ->orderBy('board_position')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        array_splice($ids, max(0, min($position, count($ids))), 0, [$deal->getKey()]);

        foreach ($ids as $index => $id) {
            if ($id === $deal->getKey()) {
                $deal->board_position = $index;

                continue;
            }

            Deal::query()->whereKey($id)->update(['board_position' => $index]);
        }
    }
}

<?php

namespace App\Actions\Sales;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Models\Lead;
use InvalidArgumentException;

class ChangeLeadStatus
{
    public function handle(Lead $lead, LeadStatus $status, ?string $unqualifiedReason = null, ?int $position = null): Lead
    {
        if ($lead->isConverted()) {
            throw new InvalidArgumentException('Converted leads can’t be changed.');
        }

        if ($status === LeadStatus::Converted) {
            throw new InvalidArgumentException('Use Convert to turn a lead into a deal.');
        }

        $from = $lead->status;

        $lead->status = $status;
        $lead->unqualified_reason = $status === LeadStatus::Unqualified ? $unqualifiedReason : null;

        if ($position !== null) {
            $this->reorder($lead, $status, $position);
        }

        $lead->save();

        if ($from !== $status) {
            $lead->logActivity(
                type: ActivityType::StageChange,
                body: $status === LeadStatus::Unqualified && filled($unqualifiedReason) ? "Reason: {$unqualifiedReason}" : null,
                title: "{$from->getLabel()} → {$status->getLabel()}",
            );
        }

        return $lead;
    }

    protected function reorder(Lead $lead, LeadStatus $status, int $position): void
    {
        $ids = Lead::query()
            ->where('status', $status)
            ->whereKeyNot($lead->getKey())
            ->orderBy('board_position')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        array_splice($ids, max(0, min($position, count($ids))), 0, [$lead->getKey()]);

        foreach ($ids as $index => $id) {
            if ($id === $lead->getKey()) {
                $lead->board_position = $index;

                continue;
            }

            Lead::query()->whereKey($id)->update(['board_position' => $index]);
        }
    }
}

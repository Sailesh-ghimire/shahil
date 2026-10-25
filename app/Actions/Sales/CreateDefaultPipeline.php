<?php

namespace App\Actions\Sales;

use App\Enums\StageKind;
use App\Models\Pipeline;
use App\Models\Team;

/**
 * Every new workspace starts with a "Sales" pipeline. Owners and admins can edit it.
 */
class CreateDefaultPipeline
{
    /**
     * @var list<array{0: string, 1: int, 2: StageKind}>
     */
    public const STAGES = [
        ['Prospecting', 10, StageKind::Open],
        ['Proposal', 40, StageKind::Open],
        ['Negotiation', 70, StageKind::Open],
        ['Won', 100, StageKind::Won],
        ['Lost', 0, StageKind::Lost],
    ];

    public function handle(Team $team): Pipeline
    {
        $pipeline = Pipeline::query()->withoutGlobalScopes()->create([
            'team_id' => $team->getKey(),
            'name' => 'Sales',
            'is_default' => true,
        ]);

        foreach (self::STAGES as $sort => [$name, $probability, $kind]) {
            $pipeline->stages()->create([
                'team_id' => $team->getKey(),
                'name' => $name,
                'probability' => $probability,
                'kind' => $kind,
                'sort' => $sort,
            ]);
        }

        return $pipeline;
    }
}

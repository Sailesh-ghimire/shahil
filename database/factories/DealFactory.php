<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use forTeam(): a deal needs the team's pipeline and first stage.
 *
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->catchPhrase(),
            'amount' => fake()->numberBetween(50_000, 2_000_000) * 100,
            'expected_close_on' => now()->addDays(fake()->numberBetween(5, 60)),
        ];
    }

    public function forTeam(Team $team, ?string $stageName = null): static
    {
        return $this->state(function () use ($team, $stageName): array {
            $pipeline = Pipeline::query()->withoutGlobalScopes()->where('team_id', $team->getKey())->orderByDesc('is_default')->firstOrFail();
            $stages = $pipeline->stages()->withoutGlobalScopes()->get();
            $stage = $stageName === null ? $stages->first() : $stages->firstWhere('name', $stageName);

            return [
                'team_id' => $team->getKey(),
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $stage->getKey(),
            ];
        });
    }
}

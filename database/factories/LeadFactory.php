<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Enums\RecordSource;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+97798'.fake()->numerify('########'),
            'company_name' => fake()->company(),
            'source' => fake()->randomElement(RecordSource::cases()),
            'status' => LeadStatus::New,
            'estimated_value' => fake()->numberBetween(10_000, 500_000) * 100,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->getKey()]);
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}

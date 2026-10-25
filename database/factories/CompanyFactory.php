<?php

namespace Database\Factories;

use App\Enums\CompanySize;
use App\Models\Company;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->company(),
            'industry' => fake()->randomElement(['Logistics', 'FMCG', 'Travel', 'Manufacturing', 'IT services']),
            'website' => fake()->url(),
            'phone' => '+97714'.fake()->numerify('######'),
            'address' => fake()->city(),
            'size' => fake()->randomElement(CompanySize::cases()),
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->getKey()]);
    }
}

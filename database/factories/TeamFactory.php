<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bare team rows. Use the CreateWorkspace action (or TestCase::createWorkspace) when roles are needed.
 *
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'NPR',
            'timezone' => 'Asia/Kathmandu',
        ];
    }
}

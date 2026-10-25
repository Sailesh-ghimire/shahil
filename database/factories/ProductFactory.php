<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('??-###-##')),
            'name' => fake()->words(3, true),
            'unit_price' => fake()->numberBetween(1_000, 200_000) * 100,
            'tax_rate' => 13,
            'is_active' => true,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->getKey()]);
    }
}

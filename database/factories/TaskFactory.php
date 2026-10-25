<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => 'Call '.fake()->firstName(),
            'type' => TaskType::Call,
            'due_at' => now()->addDay(),
            'remind_minutes_before' => 15,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->getKey()]);
    }
}

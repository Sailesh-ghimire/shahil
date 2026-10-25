<?php

namespace Database\Factories;

use App\Enums\ContactMethodType;
use App\Enums\RecordSource;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'source' => fake()->randomElement(RecordSource::cases()),
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->getKey()]);
    }

    public function withEmail(?string $email = null): static
    {
        return $this->afterCreating(fn (Contact $contact) => $contact->methods()->create([
            'team_id' => $contact->team_id,
            'type' => ContactMethodType::Email,
            'value' => $email ?? fake()->unique()->safeEmail(),
            'is_primary' => true,
        ]));
    }

    public function withPhone(?string $phone = null): static
    {
        return $this->afterCreating(fn (Contact $contact) => $contact->methods()->create([
            'team_id' => $contact->team_id,
            'type' => ContactMethodType::Phone,
            'value' => $phone ?? '+97798'.fake()->numerify('########'),
            'is_primary' => true,
        ]));
    }
}

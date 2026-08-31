<?php

namespace Database\Factories;

use App\Models\ClubEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubEventRegistration>
 *
 * NOTE: App\Models\ClubEventRegistration does not use the HasFactory trait, so
 * ClubEventRegistration::factory() is unavailable. Instantiate this factory
 * directly instead: ClubEventRegistrationFactory::new()->create([...]).
 */
class ClubEventRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => ClubEvent::factory(),
            'user_id' => User::factory(),
            'role' => 'participant',
            'status' => 'joined',
            'paid' => false,
            'entry_channel' => 'individual',
            'registered_at' => now(),
        ];
    }

    /**
     * Indicate that the entry was made by a club on the athlete's behalf.
     */
    public function viaClub(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'entry_channel' => 'club',
            'representing_tenant_id' => $tenantId,
        ]);
    }

    /**
     * Indicate that the entry fee has been settled.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => true,
            'paid_at' => now(),
        ]);
    }
}

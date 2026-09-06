<?php

namespace Database\Factories;

use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubEvent>
 */
class ClubEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'tenant_id' => Tenant::factory(),
            // The model's creating() hook also assigns one; setting it here
            // means a ->make() (which never fires that hook) still has a uuid.
            'uuid' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'date' => $date->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'location' => fake()->city(),
            'event_type' => 'championship',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'spots_taken' => 0,
            'max_capacity' => 32,
            'minutes_per_match' => 8,
            'break_minutes' => 60,
            'courts' => 2,
            'spectator_enabled' => false,
            'fee_currency' => 'BHD',
        ];
    }

    /**
     * Indicate that the event is open to clubs beyond the host.
     */
    public function nationwide(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'nationwide',
        ]);
    }

    /**
     * Indicate that the event has already finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'date' => now()->subWeek()->format('Y-m-d'),
        ]);
    }
}

<?php

namespace Database\Factories\Clubs\Models;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Clubs\Models\ClubActivity>
 */
class ClubActivityFactory extends Factory
{
    /** @var class-string<\App\Clubs\Models\ClubActivity> */
    protected $model = ClubActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Taekwondo', 'Boxing', 'Yoga', 'Swimming', 'Padel', 'Fitness']),
            'style' => null,
            'duration_minutes' => 60,
            'frequency_per_week' => 3,
            'facility_id' => null,
            'schedule' => [],
            'description' => fake()->sentence(),
        ];
    }
}

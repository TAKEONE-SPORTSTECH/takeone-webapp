<?php

namespace Database\Factories\Clubs\Models;

use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Clubs\Models\ClubInstructor>
 */
class ClubInstructorFactory extends Factory
{
    /** @var class-string<\App\Clubs\Models\ClubInstructor> */
    protected $model = ClubInstructor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // (tenant_id, user_id) is uniquely indexed — one row per person
            // per club.
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => 'Instructor',
            'staff_type' => 'instructor',
            'rating' => 0,
            'sort_order' => 0,
            'compensation_type' => ClubInstructor::COMPENSATION_VOLUNTEER,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the staff member is on a wage.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => fake()->randomFloat(2, 100, 900),
            'wage_period' => 'monthly',
            'paid_since' => now(),
        ]);
    }
}

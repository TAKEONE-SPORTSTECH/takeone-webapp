<?php

namespace Database\Factories\Clubs\Models;

use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Clubs\Models\ClubPackage>
 */
class ClubPackageFactory extends Factory
{
    /** @var class-string<\App\Clubs\Models\ClubPackage> */
    protected $model = ClubPackage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Beginner', 'Intermediate', 'Advanced', 'Junior']).' '.fake()->word(),
            // 'type' and 'gender' are DB check constraints on club_packages:
            // type in (single, multi), gender in (mixed, male, female).
            // These are package audience values, not the person-level
            // Male/Female vocabulary used on users.
            'type' => 'single',
            'gender' => 'mixed',
            'age_min' => 6,
            'age_max' => 60,
            'price' => fake()->randomFloat(2, 10, 120),
            'registration_fee' => 0,
            'duration_months' => 1,
            'session_count' => 12,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the package is no longer offered.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories\Members\Models;

use App\Clubs\Models\Tenant;
use App\Members\Models\Membership;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Members\Models\Membership>
 */
class MembershipFactory extends Factory
{
    /** @var class-string<\App\Members\Models\Membership> */
    protected $model = Membership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the membership is no longer active.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}

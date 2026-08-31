<?php

namespace Database\Factories;

use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubMemberSubscription>
 */
class ClubMemberSubscriptionFactory extends Factory
{
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
            // Keep the package under the same club as the subscription, so a
            // created row is internally consistent for club-scoped queries.
            'package_id' => fn (array $attributes) => ClubPackage::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'type' => 'regular',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            // active/pending occupy the dedup slot: the model's booted() hook
            // derives active_key from (tenant, user, package), which is
            // uniquely indexed. Two active subscriptions for the same trio
            // will collide by design.
            'status' => 'active',
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'amount_due' => fake()->randomFloat(2, 10, 120),
            'registration_fee' => 0,
        ];
    }

    /**
     * Indicate that the subscription has been paid in full.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'amount_paid' => $attributes['amount_due'] ?? 0,
            'amount_due' => 0,
            'settled_at' => now(),
        ]);
    }

    /**
     * Indicate that the subscription has lapsed (frees the dedup slot).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }
}

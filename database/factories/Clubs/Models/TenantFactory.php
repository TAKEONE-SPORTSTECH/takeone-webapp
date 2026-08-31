<?php

namespace Database\Factories\Clubs\Models;

use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Clubs\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /** @var class-string<\App\Clubs\Models\Tenant> */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' '.fake()->randomElement(['Club', 'Academy', 'Center']);

        return [
            'owner_user_id' => User::factory(),
            'club_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'slogan' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => [fake()->numerify('+973 3###-####')],
            'currency' => 'BHD',
            'timezone' => 'Asia/Bahrain',
            // ISO code, never a full country name — the public club URL is
            // /{country}/clubs/{slug} and a full name 404s.
            'country' => 'BH',
            'address' => fake()->address(),
            'enrollment_fee' => 0,
            'registration_fee' => 0,
            'vat_percentage' => 0,
            'status' => 'active',
            'public_profile_enabled' => true,
        ];
    }

    /**
     * Indicate that the club is not publicly listed.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'public_profile_enabled' => false,
        ]);
    }

    /**
     * Indicate that the club is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}

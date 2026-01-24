<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Membership;
use App\Models\Invoice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'full_name' => 'Test User',
            'mobile' => ['code' => '+1', 'number' => '1234567890'],
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create a tenant (club)
        $tenant = Tenant::create([
            'owner_user_id' => $user->id,
            'club_name' => 'Test Club',
            'slug' => 'test-club',
            'gps_lat' => 25.276987, // Dubai latitude
            'gps_long' => 55.296249, // Dubai longitude
        ]);

        // Create membership
        Membership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        // Create a paid invoice
        Invoice::create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $user->id,
            'payer_user_id' => $user->id,
            'amount' => 50.00,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
        ]);

        // Create a pending invoice
        Invoice::create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $user->id,
            'payer_user_id' => $user->id,
            'amount' => 25.00,
            'status' => 'pending',
            'due_date' => now()->addDays(15),
        ]);

        // Seed attendance data
        $this->call(AttendanceSeeder::class);
    }
}

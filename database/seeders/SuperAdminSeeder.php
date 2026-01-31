<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@takeone.com'],
            [
                'name' => 'Super Administrator',
                'full_name' => 'Super Administrator',
                'password' => Hash::make('SuperAdmin@2024'),
                'email_verified_at' => now(),
                'mobile' => ['code' => '+971', 'number' => '501234567'],
                'gender' => 'male',
                'nationality' => 'UAE',
            ]
        );

        // Assign super-admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if ($superAdminRole) {
            // Check if role is already assigned
            if (!$superAdmin->hasRole('super-admin')) {
                $superAdmin->roles()->attach($superAdminRole->id, ['tenant_id' => null]);
                $this->command->info('Super admin role assigned successfully!');
            } else {
                $this->command->info('User already has super admin role.');
            }
        } else {
            $this->command->error('Super admin role not found. Please run RolePermissionSeeder first.');
        }

        $this->command->info('Super Admin User Created:');
        $this->command->info('Email: superadmin@takeone.com');
        $this->command->info('Password: SuperAdmin@2024');
    }
}

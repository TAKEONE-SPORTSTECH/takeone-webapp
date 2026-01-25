<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            // Platform Admin Permissions
            ['name' => 'Manage All Clubs', 'slug' => 'manage-all-clubs', 'description' => 'Create, edit, delete any club'],
            ['name' => 'Manage All Members', 'slug' => 'manage-all-members', 'description' => 'View and manage all platform members'],
            ['name' => 'Database Backup', 'slug' => 'database-backup', 'description' => 'Backup and restore database'],
            ['name' => 'View Platform Analytics', 'slug' => 'view-platform-analytics', 'description' => 'View platform-wide analytics'],

            // Club Admin Permissions
            ['name' => 'Manage Club Details', 'slug' => 'manage-club-details', 'description' => 'Edit club information and settings'],
            ['name' => 'Manage Facilities', 'slug' => 'manage-facilities', 'description' => 'Create, edit, delete facilities'],
            ['name' => 'Manage Instructors', 'slug' => 'manage-instructors', 'description' => 'Add, edit, remove instructors'],
            ['name' => 'Manage Activities', 'slug' => 'manage-activities', 'description' => 'Create, edit, delete activities'],
            ['name' => 'Manage Packages', 'slug' => 'manage-packages', 'description' => 'Create, edit, delete packages'],
            ['name' => 'Manage Members', 'slug' => 'manage-members', 'description' => 'Add, edit, remove club members'],
            ['name' => 'Manage Financials', 'slug' => 'manage-financials', 'description' => 'View and manage club finances'],
            ['name' => 'Manage Gallery', 'slug' => 'manage-gallery', 'description' => 'Upload and manage gallery images'],
            ['name' => 'Manage Messages', 'slug' => 'manage-messages', 'description' => 'Send and receive messages'],
            ['name' => 'View Analytics', 'slug' => 'view-analytics', 'description' => 'View club analytics and reports'],

            // Instructor Permissions
            ['name' => 'View Members', 'slug' => 'view-members', 'description' => 'View club members'],
            ['name' => 'Mark Attendance', 'slug' => 'mark-attendance', 'description' => 'Mark member attendance'],
            ['name' => 'Send Messages', 'slug' => 'send-messages', 'description' => 'Send messages to members'],

            // Member Permissions
            ['name' => 'View Own Profile', 'slug' => 'view-own-profile', 'description' => 'View own profile and subscriptions'],
            ['name' => 'Update Own Profile', 'slug' => 'update-own-profile', 'description' => 'Update own profile information'],
            ['name' => 'View Club Info', 'slug' => 'view-club-info', 'description' => 'View club information'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        // Create Roles
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Platform administrator with full access',
                'permissions' => [
                    'manage-all-clubs',
                    'manage-all-members',
                    'database-backup',
                    'view-platform-analytics',
                ]
            ],
            [
                'name' => 'Club Admin',
                'slug' => 'club-admin',
                'description' => 'Club owner/administrator with full club access',
                'permissions' => [
                    'manage-club-details',
                    'manage-facilities',
                    'manage-instructors',
                    'manage-activities',
                    'manage-packages',
                    'manage-members',
                    'manage-financials',
                    'manage-gallery',
                    'manage-messages',
                    'view-analytics',
                ]
            ],
            [
                'name' => 'Instructor',
                'slug' => 'instructor',
                'description' => 'Club instructor with limited access',
                'permissions' => [
                    'view-members',
                    'mark-attendance',
                    'send-messages',
                    'view-club-info',
                ]
            ],
            [
                'name' => 'Member',
                'slug' => 'member',
                'description' => 'Club member with basic access',
                'permissions' => [
                    'view-own-profile',
                    'update-own-profile',
                    'view-club-info',
                ]
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                ]
            );

            // Attach permissions to role
            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application to its baseline production state:
     *   1. Roles + permissions (global auth vocabulary)
     *   2. The super-admin user
     *   3. The global activity catalog (shared, not tenant-scoped)
     *   4. The flagship club "TAKEONE SportsTech" (owned by the super-admin) with
     *      realistic activities + packages that reference the global activities.
     *
     * No members, no demo clubs, no test data — a clean, presentable baseline.
     * Every seeder here is idempotent (firstOrCreate), so `php artisan db:seed`
     * is safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            ActivityCatalogSeeder::class,
            TakeOneSportsTechSeeder::class,
        ]);
    }
}

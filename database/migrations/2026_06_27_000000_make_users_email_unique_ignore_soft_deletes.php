<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make the users.email uniqueness apply only to ACTIVE (non-soft-deleted)
     * users. Once a member is deleted their email is freed, so the same person
     * can register again. Implemented as a partial unique index where supported
     * (sqlite/pgsql); on MySQL the app-layer validation handles it.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');
        }
    }
};

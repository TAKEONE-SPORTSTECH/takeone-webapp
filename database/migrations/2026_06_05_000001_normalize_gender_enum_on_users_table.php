<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing single-letter values to full words
        DB::statement("UPDATE users SET gender = 'Male'   WHERE gender = 'm'");
        DB::statement("UPDATE users SET gender = 'Female' WHERE gender = 'f'");

        // MySQL/MariaDB: re-declare enum; SQLite has no enum type so skip
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN gender ENUM('Male','Female') NULL");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET gender = 'm' WHERE gender = 'Male'");
        DB::statement("UPDATE users SET gender = 'f' WHERE gender = 'Female'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN gender ENUM('m','f') NULL");
        }
    }
};

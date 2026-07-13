<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a club owner hide a coach from the public club page without removing them
 * (matches the visibility toggle the other sections have).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

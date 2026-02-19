<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_package_activities', function (Blueprint $table) {
            $table->json('schedule')->nullable()->after('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_package_activities', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }
};

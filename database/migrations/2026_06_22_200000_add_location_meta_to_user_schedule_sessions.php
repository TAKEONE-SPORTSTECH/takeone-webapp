<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Structured location for a personal session: {type:'map'|'text', lat, lng, address}. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_schedule_sessions', function (Blueprint $table) {
            $table->json('location_meta')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('user_schedule_sessions', function (Blueprint $table) {
            $table->dropColumn('location_meta');
        });
    }
};

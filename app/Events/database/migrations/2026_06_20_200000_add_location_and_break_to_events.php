<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            // Location: either map coordinates (+ address in `location`) or a Maps URL.
            $table->decimal('gps_lat', 10, 7)->nullable()->after('location');
            $table->decimal('gps_long', 10, 7)->nullable()->after('gps_lat');
            $table->string('location_url')->nullable()->after('gps_long');
            // Daily break window (championship court scheduling).
            $table->time('break_start')->nullable()->after('break_minutes');
            $table->time('break_end')->nullable()->after('break_start');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['gps_lat', 'gps_long', 'location_url', 'break_start', 'break_end']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            // Result format: single | bo3 | bo5 | points | time
            $table->string('format', 20)->default('single')->after('metric');
            // Per-round winners (bo3/bo5): array of 'challenger'|'opponent'. Null for non-round formats.
            $table->json('rounds')->nullable()->after('opponent_score');
            // Location (location text already exists): maps link + pinned coordinates.
            $table->string('location_url')->nullable()->after('location');
            $table->decimal('gps_lat', 10, 7)->nullable()->after('location_url');
            $table->decimal('gps_long', 10, 7)->nullable()->after('gps_lat');
        });
    }

    public function down(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->dropColumn(['format', 'rounds', 'location_url', 'gps_lat', 'gps_long']);
        });
    }
};

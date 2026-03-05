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
        Schema::table('club_facilities', function (Blueprint $table) {
            $table->string('maps_url', 500)->nullable()->after('gps_long');
        });
    }

    public function down(): void
    {
        Schema::table('club_facilities', function (Blueprint $table) {
            $table->dropColumn('maps_url');
        });
    }
};

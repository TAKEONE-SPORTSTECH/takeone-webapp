<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Number of mats/courts available (owner override; falls back to the per-day suggestion). */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('courts')->nullable()->after('minutes_per_match');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('courts');
        });
    }
};

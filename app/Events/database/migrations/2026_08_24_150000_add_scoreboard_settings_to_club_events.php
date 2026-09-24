<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a mat's rules live between bouts.
 *
 * They used to live only in the mat's cache entry, beside the score — which is
 * right for a bout and wrong for a competition: the entry has a four-hour TTL,
 * so an official who set the gap and the bout length in the morning found them
 * back at their defaults after a long lunch, and a cache clear wiped them for
 * every mat at once.
 *
 * Additive and nullable: an event with nothing here behaves exactly as it did
 * before, because the defaults ARE the previous hard-coded values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->json('scoreboard_settings')->nullable()->after('day_courts');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('scoreboard_settings');
        });
    }
};

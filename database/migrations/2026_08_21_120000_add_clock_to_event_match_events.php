<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHEN in the bout, not just when in the day.
 *
 * The log already records the wall clock to the millisecond, which answers "what
 * order did this happen in". It does not answer the question a competition
 * report is actually written around: "Ippon to AKA at 1:32". Two nullable
 * columns beside the rest — the seconds left on the bout clock as the command
 * landed, and the bout length it was counting down from, so a reading is
 * interpretable years later even if the division's bout length changes.
 *
 * Nullable because a command that arrives with no bout on the mat (a resync, a
 * screen being told to reload) has no clock to report, and because every row
 * already written predates this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_match_events', function (Blueprint $table) {
            $table->decimal('clock_remaining', 6, 2)->nullable()->after('points');
            $table->decimal('clock_duration', 6, 2)->nullable()->after('clock_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('event_match_events', function (Blueprint $table) {
            $table->dropColumn(['clock_remaining', 'clock_duration']);
        });
    }
};

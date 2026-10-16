<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each SESSION of a running order calls its bouts (match numbering).
 *
 * A session is the stretch of a day between breaks. Its method lives on the
 * break that opens it (`event_run_slots.mode`, break rows only), and the first
 * session of each day — which no break opens — on the event
 * (`club_events.run_modes`, {"day": "mode"}). Moving a break moves the
 * boundary and the method travels with it.
 *
 *   class  — one class at a time (what every running order did before; null
 *            means this, so existing events number exactly as they did)
 *   round  — round by round across the session's classes (World Taekwondo)
 *   manual — the session keeps the order its bouts have; the organiser
 *            changes it on the bout-order board
 *
 * Additive and nullable (RULE #1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->string('mode', 16)->nullable()->after('kind');
        });

        Schema::table('club_events', function (Blueprint $table) {
            $table->json('run_modes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->dropColumn('mode');
        });

        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('run_modes');
        });
    }
};

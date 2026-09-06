<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the draw becomes readable — the organiser's call.
 *
 * A published bracket is a decision with a clock on it. Some organisers want it
 * up the moment it is built; plenty want it withheld until the morning, because
 * a draw seen a week early is a week of athletes rearranging their preparation
 * around one name, and because a draw that is still being fixed should not be
 * read as final.
 *
 * ONE column, three answers, and the first of them is exactly what every event
 * does today — so nothing already running changes:
 *
 *   always     the draw is readable as soon as it exists (the default, and the
 *              behaviour of every event that existed before this column)
 *   start_day  concealed until the event's own date arrives, or until the
 *              organiser presses start — whichever comes first
 *   hidden     concealed until the organiser says otherwise, with no clock
 *
 * `hidden` is what makes the switch manual in both directions: an organiser can
 * put the draw up early by choosing `always`, or take it back down mid-week by
 * choosing `hidden`, without the setting meaning something different afterwards.
 *
 * It never hides anything from the people running the event: the organiser and
 * every appointed official read the draw at all three settings, because they are
 * the ones building it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->string('draw_reveal', 16)->default('always')->after('public_entry_auto_accept');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('draw_reveal');
        });
    }
};

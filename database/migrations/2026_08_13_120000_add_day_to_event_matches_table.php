<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which competition day a bout belongs to.
 *
 * The scheduler has always known this — it buckets bouts by day and numbers
 * them PER MAT PER DAY, so Mat 1 restarts at bout 1 every morning, as the
 * callers announce it. But the day lived only in event_categories.schedule
 * JSON, and nothing downstream could see it: RunningOrder ordered a mat's queue
 * by (court, match_no) alone, so a two-day event interleaved both days into one
 * list with duplicate bout numbers. The wall board announced GET READY over a
 * bout scheduled for the following morning, "bouts ahead" counted tomorrow's
 * bouts into today's ETA, and the scoring table's next-bout could load one.
 *
 * Storing the resolved day on the bout is what makes the running order
 * expressible as (day, court, match_no) — unique, and orderable in SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            // Nullable, like court and match_no, and for the same reason: a bye
            // is decided at draw time, never runs on a mat, and is not part of
            // any day's running order.
            $table->unsignedTinyInteger('day')->nullable()->after('court');
        });

        // The queue is read by (event, day, court, match_no) on every board
        // repaint and every athlete's countdown.
        Schema::table('event_matches', function (Blueprint $table) {
            $table->index(['event_id', 'day', 'court', 'match_no'], 'event_matches_running_order_index');
        });

        $this->backfill();
    }

    /**
     * Resolve each existing bout's day from its division's schedule.
     *
     * Same rule as Scheduler::phaseDay — the schedule value is either a plain
     * day number or a {day, court} object, and anything missing means day 1.
     * Bouts with no mat are byes and stay null.
     */
    private function backfill(): void
    {
        $schedules = DB::table('event_categories')->pluck('schedule', 'id');

        DB::table('event_matches')
            ->whereNotNull('court')
            ->orderBy('id')
            ->chunkById(500, function ($matches) use ($schedules) {
                foreach ($matches as $match) {
                    $schedule = json_decode((string) ($schedules[$match->category_id] ?? ''), true) ?: [];
                    $value = $schedule[$match->phase ?: 'preliminary'] ?? null;
                    $day = (int) (is_array($value) ? ($value['day'] ?? 1) : ($value ?: 1));

                    DB::table('event_matches')->where('id', $match->id)->update(['day' => max(1, $day)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropIndex('event_matches_running_order_index');
            $table->dropColumn('day');
        });
    }
};

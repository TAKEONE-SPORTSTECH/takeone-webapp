<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The read-only mirror of a bout's video timeline, as annotated on TAKEONE Play.
 *
 * Play OWNS these rows (Match Sync Contract, Flow B): takeone pulls them and
 * replaces its copy wholesale, and never writes back. That one-way rule is what
 * makes the sync conflict-free — there is no path by which a change returns to
 * its own author — so nothing in the app may update these tables except the
 * mirroring action.
 *
 * Keyed on event_recordings, because that row is the join takeone already owns
 * (it carries play_video_id / play_video_key). Cascading on delete is safe here
 * and only here: the mirror is a cache of someone else's data, so dropping the
 * recording should drop the copy. Nothing about the BOUT depends on it.
 *
 * Entirely additive. With PLAY_INTEGRATION_ENABLED off these tables simply stay
 * empty and every existing screen behaves exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_timeline_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_recording_id')->constrained('event_recordings')->cascadeOnDelete();

            // Play's own primary key, kept so a pull can be diffed against the
            // previous one while debugging. Not a foreign key: it points into
            // another database.
            $table->unsignedBigInteger('play_round_id')->index();

            $table->unsignedInteger('round_number')->nullable();
            $table->string('name')->nullable();
            // Seconds into the video. Nullable because Play leaves it null until
            // someone sets a round's start marker.
            $table->decimal('start_time_seconds', 12, 3)->nullable();

            $table->timestamps();

            $table->unique(['event_recording_id', 'play_round_id']);
        });

        Schema::create('play_timeline_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_recording_id')->constrained('event_recordings')->cascadeOnDelete();
            $table->unsignedBigInteger('play_point_id')->index();
            $table->unsignedBigInteger('play_round_id')->nullable()->index();

            $table->decimal('timestamp_seconds', 12, 3)->nullable();
            $table->string('action')->nullable();
            $table->integer('points')->nullable();

            // Play's vocabulary: 'blue' / 'red'.
            $table->string('competitor', 8)->nullable();

            /*
             * The same point expressed in takeone's vocabulary — 'a' or 'b' —
             * resolved through the bout's recorded corners. This is the whole
             * reason a_corner/b_corner had to become stored fact: without it a
             * point cannot be attributed to an athlete, only to a colour.
             *
             * NULL when the bout has no corners recorded. Left null rather than
             * guessed: attributing a point to the wrong fighter is worse than
             * declining to attribute it.
             */
            $table->string('side', 1)->nullable();

            $table->integer('score_blue')->nullable();
            $table->integer('score_red')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['event_recording_id', 'play_point_id']);
            $table->index(['event_recording_id', 'timestamp_seconds']);
        });

        Schema::table('event_recordings', function (Blueprint $table) {
            // Optimistic concurrency for the push direction (Flow A, step 2).
            $table->unsignedBigInteger('play_revision')->nullable()->after('status');
            $table->timestamp('pushed_at')->nullable()->after('play_revision');
            $table->timestamp('timeline_pulled_at')->nullable()->after('pushed_at');
            // Last failure, so a broken sync is visible rather than silent.
            $table->text('sync_error')->nullable()->after('timeline_pulled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_timeline_points');
        Schema::dropIfExists('play_timeline_rounds');

        Schema::table('event_recordings', function (Blueprint $table) {
            $table->dropColumn(['play_revision', 'pushed_at', 'timeline_pulled_at', 'sync_error']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coach's note against a moment of a bout.
 *
 * The scoring timeline is DERIVED — it is the officiating log replayed against
 * the recording's anchor, and nobody types it twice. This table is the other
 * half: the things a human wants to say about the footage that no mat console
 * could ever know. "Guard dropped here." "This is the entry we drilled."
 *
 * Three deliberate differences from how the video platform modelled the same
 * idea, each one a bug we are choosing not to inherit:
 *
 *   1. A note hangs off the BOUT, not off a video file. A bout can be filmed
 *      from four angles and re-encoded any number of times; what the coach said
 *      about the third exchange is true of the bout, not of one mp4. Pin it to
 *      an angle only when the note is genuinely about that camera.
 *
 *   2. `position_x`/`position_y` are decimal(6,4). They hold a normalised 0..1
 *      coordinate of the overlay's centre, and the original declared them with
 *      scale ZERO — every position would have rounded to 0 or 1 the moment it
 *      left SQLite for a stricter engine.
 *
 *   3. `end_seconds >= start_seconds` is enforced server-side, not only in the
 *      browser. An inverted range there made the replay silently do nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bout_coach_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('event_matches')->cascadeOnDelete();

            // Which angle the note is about. NULL — the ordinary case — means the
            // bout itself, so the note survives a camera being unpaired or a
            // recording being replaced.
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();

            // Who wrote it, and whose name is shown. They differ on purpose: an
            // organiser reviewing footage often records what the corner coach
            // said, and the coach's name is the one that belongs on the note.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('coach_name', 100);

            $table->string('emoji', 16)->default('🔥');
            $table->text('note');

            // Media time: seconds from the first frame of the recording.
            $table->decimal('start_seconds', 10, 3);
            $table->decimal('end_seconds', 10, 3)->nullable();

            // The overlay's CENTRE as a fraction of the player, so the caption
            // sits where the coach put it at any size and in fullscreen.
            $table->decimal('position_x', 6, 4)->nullable();
            $table->decimal('position_y', 6, 4)->nullable();

            $table->timestamps();

            $table->index(['match_id', 'start_seconds']);
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bout_coach_notes');
    }
};

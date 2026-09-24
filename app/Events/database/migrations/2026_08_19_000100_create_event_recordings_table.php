<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link between a bout in takeone and its video on TAKEONE Play.
 *
 * takeone owns the competition truth; Play owns the media. This table is the
 * one place the two are joined, and it lives HERE rather than as a column on
 * `event_matches` because one bout can eventually have several recordings
 * (angles, Tier C) — Documentation/VIDEO-INTEGRATION.md §5.2.
 *
 * Strictly additive: a new table, nothing existing altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_recordings')) {
            return;
        }

        Schema::create('event_recordings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Null until a bout is on the mat, and it SURVIVES the bout being
            // deleted — losing the pointer to a published video because a draw
            // was re-cut would orphan media nobody can find again.
            $table->foreignId('match_id')->nullable()
                ->constrained('event_matches')->nullOnDelete();

            $table->string('court')->nullable();

            // main | corner_a | corner_b | overhead — one row per angle.
            $table->string('angle', 20)->default('main');

            // Wall clock of media offset 0. Media time is derived from this, so
            // a marker's position is recomputable after a re-encode (§4).
            $table->dateTime('anchor_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            // Play's side of the link. Nullable until the video exists there.
            $table->unsignedBigInteger('play_video_id')->nullable();
            $table->string('play_video_key')->nullable();   // the encoded public key
            $table->string('play_url')->nullable();

            // recording | cutting | uploading | processing | ready | failed
            // 'linked' for a video that already existed on Play and was matched
            // to a bout by hand rather than produced by a recorder.
            $table->string('status', 20)->default('recording');

            $table->timestamps();

            $table->index(['event_id', 'court']);
            $table->index('match_id');
            $table->index('play_video_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_recordings');
    }
};

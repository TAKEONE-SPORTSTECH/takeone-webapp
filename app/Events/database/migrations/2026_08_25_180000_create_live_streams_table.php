<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mat, live.
 *
 * ── What this is for ───────────────────────────────────────────────────────
 *
 * Somebody in the hall points a phone at mat 2 and presses Go Live, and a
 * parent three countries away watches the bout their child is fighting. That is
 * the whole feature. Everything else here exists to make that safe and to make
 * sure the fight is not lost the moment it ends.
 *
 * ── A stream belongs to an EVENT, not to a person ──────────────────────────
 *
 * This is the difference between this table and the one on the video platform
 * next door. There, a live stream was a user's broadcast. Here it is part of a
 * competition: it hangs off `club_events`, optionally off a mat and a bout, and
 * who may watch it is answered by the event's own visibility rule
 * (`EventAccess::visible`) rather than by a per-stream setting. An organiser is
 * not managing a channel; they are switching a mat on.
 *
 * ── Why the token is a hash ────────────────────────────────────────────────
 *
 * The publish credential is minted when somebody presses Go Live, is single-use,
 * and lives about two minutes. It is stored hashed for the same reason a password
 * is: this row is read by more code than it is written by, and a leaked database
 * copy must not let anybody broadcast onto a mat.
 *
 * ── media_file_id: the recording it leaves behind ──────────────────────────
 *
 * A stream that vanishes when it ends is a competition's footage lost in real
 * time. The media server records every broadcast, and when the phone stops, that
 * recording is ingested through the ordinary media pipeline — which means it
 * lands under the bout, on whatever storage is attached, and is transcoded like
 * any other clip. This column is where it ends up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();

            // The public handle, and the media server's path. Constrained to
            // [A-Za-z0-9] because it is matched by a regex in the media server's
            // own config — anything else would simply never be routable.
            $table->string('public_id', 32)->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Which mat, and which bout if one is loaded. Both nullable: a stream
            // is often started before the draw reaches the mat, and the bout is
            // attached as it changes.
            $table->string('court')->nullable();
            $table->foreignId('match_id')->nullable()->constrained('event_matches')->nullOnDelete();

            $table->string('title')->nullable();

            // idle | live | ended | failed
            $table->string('status', 12)->default('idle');

            // Who may watch. `event` defers to the event's own rule, which is the
            // sane default and what an organiser expects. `unlisted` is for a link
            // shared outside it.
            $table->string('visibility', 12)->default('event');

            $table->string('publish_token_hash', 64)->nullable();
            $table->timestamp('publish_token_expires_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Last time the media server said this publisher was still there. A
            // phone that loses signal never sends an "I stopped" — without this a
            // dead stream reads as live forever.
            $table->timestamp('last_seen_at')->nullable();

            $table->unsignedInteger('current_viewers')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);

            // The recording, once the broadcast has ended and been ingested.
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('recording_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};

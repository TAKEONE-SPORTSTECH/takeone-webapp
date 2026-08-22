<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A screen bolted to a wall, showing one Karate mat's running order.
 *
 * Owned by the Karate Tournament package (Karate/Tournament/CourtDisplay/), and
 * deliberately its OWN table rather than a shared one with Taekwondo.
 *
 * The two packages run separate screen fleets: separate routes (/karate/court/*
 * against /court/*), separate screen builds, separate systemd units. Sharing the
 * table would undo that — CourtDisplayDevice::resolve() looks a device up by
 * token hash alone, so Taekwondo's controller would happily resolve a Karate
 * screen and hand it a Taekwondo board, and the two would compete for the same
 * unique pairing codes. A device belongs to exactly one fleet, so it lives in
 * exactly one table.
 *
 * The shape is the merge of the two Taekwondo migrations (create + add pairing),
 * which is the schema that package actually runs today: event and court are
 * nullable because a screen exists, and shows a pairing code, before an organiser
 * has told it which mat it is looking at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karate_court_displays', function (Blueprint $table) {
            $table->id();

            // Null until an organiser claims the screen for an event and a mat.
            $table->foreignId('event_id')->nullable()->constrained('club_events')->cascadeOnDelete();
            $table->string('court', 40)->nullable();

            // Only the HASH is stored. A leaked database row cannot be replayed
            // as a device, and the plaintext exists once — at pairing time.
            $table->string('token_hash', 64)->unique();
            // First few characters, for showing an organiser WHICH screen a row
            // is without ever printing something usable.
            $table->string('token_hint', 12);

            // Short, human-readable, and shown on a screen in a public hall —
            // so it is worthless on its own: claiming with it requires an
            // organiser who can already manage the event.
            $table->string('pairing_code', 12)->nullable()->unique();

            $table->string('label', 60)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'court']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karate_court_displays');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A screen bolted to a wall, showing one Brazilian Jiu-Jitsu mat.
 *
 * Package-owned, and deliberately its OWN table rather than one shared with
 * Karate or Taekwondo — for exactly the reason set out in
 * 2026_08_13_100000_create_karate_court_displays_table.php: a device resolves
 * by token hash alone, so a shared table would let one package's controller
 * resolve another package's screen and hand it the wrong board, and the three
 * fleets would compete for the same unique pairing codes. A device belongs to
 * one fleet, so it lives in one table.
 *
 * Shape is the same as the sibling fleets', which is what lets the shared
 * HallScreenRouter treat all three identically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bjj_screen_devices', function (Blueprint $table) {
            $table->id();

            // Null until an organiser claims the screen for an event and a mat.
            $table->foreignId('event_id')->nullable()->constrained('club_events')->cascadeOnDelete();
            $table->string('court', 40)->nullable();

            // What this screen is FOR: 'bout' (the mat board), 'queue' (the
            // running order) or 'control' (the scoring table). Null means
            // "follow the mat".
            $table->string('surface', 12)->nullable();

            // Only the HASH is stored — a leaked row cannot be replayed as a
            // device, and the plaintext exists once, at pairing time.
            $table->string('token_hash', 64)->unique();
            $table->string('token_hint', 12);

            // Read aloud across a hall; worthless on its own, because claiming
            // with it requires an organiser who can already manage the event.
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
        Schema::dropIfExists('bjj_screen_devices');
    }
};

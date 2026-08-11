<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A screen bolted to a wall, showing one mat's running order.
 *
 * Owned by the Taekwondo Tournament package (CourtDisplay/). A hall screen has
 * nobody logged in to it, so a device is its own credential: it holds a random
 * token, that token is scoped to exactly one event and one court, and it can
 * read that board and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('court', 40);

            // Only the HASH is stored. A leaked database row cannot be replayed
            // as a device, and the plaintext exists once — at pairing time.
            $table->string('token_hash', 64)->unique();
            // First few characters, for showing an organiser WHICH screen a row
            // is without ever printing something usable.
            $table->string('token_hint', 12);

            $table->string('label', 60)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'court']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_displays');
    }
};

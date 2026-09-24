<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A screen that exists but does not yet know what it is.
 *
 * Every fleet in the product is per package by design — Taekwondo's screens and
 * Karate's are separate tables, separate tokens, separate screen builds, so one
 * token set can never hand a Karate screen a Taekwondo board. That is right for
 * a screen that HAS a job. It is wrong for the moment before.
 *
 * A person walking into a hall with a television does not know, and should not
 * have to know, which package will end up owning it. They open one address. The
 * screen stands there with a code. An organiser scans it and says "Mat 2's
 * scoreboard, on the Grand Prix" — and only THEN is there enough information to
 * know which fleet it belongs to.
 *
 * So this table holds only that waiting room: a token, a code, and once claimed,
 * the address of the real screen it became. Nothing here can display anything.
 * The row is spent the moment it is claimed and the screen has moved on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_screens', function (Blueprint $table) {
            $table->id();

            // Hashed, like every screen token in the product: the lookup is a
            // single indexed read and a plaintext token is never stored.
            $table->string('token_hash', 64)->unique();
            $table->string('token_hint', 8)->nullable();

            // Read aloud across a hall and typed by hand when a camera will not
            // focus. Freed on claim so codes cannot pile up.
            $table->string('pairing_code', 6)->nullable()->unique();

            // Where the screen goes once it has been told what it is — the board
            // URL of the real device, in whichever package now owns it.
            $table->text('destination')->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_screens');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has opened an event's public page.
 *
 * One row per VISITOR per event, not per page view — see App\Events\Models\
 * EventVisit for what is stored and what deliberately is not (no raw IP, no
 * full user agent, no browsing history).
 *
 * Additive: a new table, nothing altered. Every event that exists simply has no
 * rows until somebody opens its page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            // Set when they were signed in. Nullable and stays nullable: most
            // visitors to a public poster have no account, and inventing one
            // for them is exactly what this must not do.
            $table->unsignedBigInteger('user_id')->nullable();

            // A one-way digest of either a first-party cookie or the request's
            // own shape. 64 hex characters of sha256.
            $table->string('visitor_key', 64);

            // Recorded so the excluded number can be SHOWN — an organiser who
            // is told "412 people" believes it more readily when they are also
            // told "and 180 automated fetches were left out".
            $table->boolean('is_bot')->default(false);
            $table->string('bot_name', 40)->nullable();

            $table->string('device', 10)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('referrer_host', 120)->nullable();

            $table->unsignedInteger('visits')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // The identity of a visit: one person, one event, one row. The
            // recorder upserts on this, so a reload is an increment rather than
            // a second person.
            $table->unique(['event_id', 'visitor_key']);
            // "Real people on this event, newest first" — the query the
            // organiser's panel makes every time it opens.
            $table->index(['event_id', 'is_bot', 'last_seen_at']);
            // "Which of my entrants have looked at it" joins on this.
            $table->index(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_visits');
    }
};

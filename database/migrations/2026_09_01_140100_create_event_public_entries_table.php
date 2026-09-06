<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somebody with no account asking to compete.
 *
 * Phase C of Documentation/EVENTS-PUBLIC-ENTRY.md, Door C. The spec sketched
 * this as a `pending_review` row in `club_event_registrations`; it is a table
 * of its own instead, and deliberately.
 *
 * `club_event_registrations` IS the entry list. Thirty-nine places across the
 * platform read it as "who is competing" — the roster, the draw, the entrant
 * count, the mats, the invoices — and none of them filter on a state that did
 * not exist until today. Putting an unreviewed stranger in that table means
 * every one of those places is one forgotten `where` away from seeding a bot
 * into a bracket. A request is not an entry until an organiser says it is, so
 * it waits somewhere else and becomes a real registration on accept.
 *
 * Consequence, and it is the right one: a pending request counts toward
 * nothing. Not the entrant count, not capacity, not the money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_public_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('event_id')->index();
            // The account created (or matched) for the person asking. A real
            // person record from the start — they signed themselves up.
            $table->unsignedBigInteger('user_id')->index();

            // What they told us about themselves. Held here rather than written
            // straight onto the profile so a declined request leaves no trace
            // on anybody's record, and an accept applies it in one place.
            $table->date('birthdate')->nullable();
            $table->string('gender', 16)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('belt_colour', 32)->nullable();
            // Phase D fills this from the club they name; null means unattached.
            $table->unsignedBigInteger('representing_tenant_id')->nullable();

            // pending | accepted | declined
            $table->string('state', 16)->default('pending')->index();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            // The registration this became, once it was accepted.
            $table->unsignedBigInteger('registration_id')->nullable();

            // Audit for a door a stranger can push on.
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'state']);
            // One person, one request per event — a refresh or a double tap
            // must not become two entries.
            $table->unique(['event_id', 'user_id'], 'epe_event_user_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_public_entries');
    }
};

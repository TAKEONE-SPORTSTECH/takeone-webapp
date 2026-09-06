<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clubs standing behind an event.
 *
 * Promoted out of `app/EventLab/` on 2026-09-06 after being proven there. The
 * table it replaces, `sandbox_event_clubs`, is deliberately LEFT WHERE IT IS:
 * the lab keeps working, and the two diverge from this moment the way
 * `PublicEntry` already has. Renaming the sandbox table would have been tidier
 * and would have broken `/testcode` (RULE #1).
 *
 * It exists because a competition's clubs arrive in two very different states
 * and the platform only had a name for one of them.
 *
 *  1. **A club that is already here.** It has a tenant row, an owner, a logo
 *     somebody uploaded. The organiser does not describe it — they INVITE it,
 *     and the club answers. `tenant_id` names it and `state` records the answer.
 *
 *  2. **A club that is not.** It exists in the sport but not on this platform:
 *     a team turns up with a coach, a name and a crest. Refusing to record it
 *     until somebody registers an account is how a start list ends up saying
 *     "unattached" for half the hall. So the organiser writes down what they
 *     know — a name, a logo, and an Instagram page if that is the only address
 *     the club has — and the competition runs on that.
 *
 * The second may later BECOME the first: `tenant_id` is filled in when the row
 * is matched to a club that already existed, and `promoted_tenant_id` when a
 * real club is created from it after the event (App\Events\Support\Clubs\
 * ClubPromotion). Both are plain references, never foreign keys, so nothing
 * here can drag a real tenant along behind it when an event is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_clubs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Set when this row IS a club the platform already knows.
            $table->unsignedBigInteger('tenant_id')->nullable();

            // What the organiser wrote down. `name` is the only thing a club
            // cannot be recorded without — a crest with no name names nothing.
            $table->string('name');
            $table->string('logo')->nullable();

            // The one address many small clubs actually have. Stored as a full
            // https://instagram.com/… URL, normalised on the way in, never as
            // whatever was pasted.
            $table->string('instagram')->nullable();
            $table->string('country', 3)->nullable();

            // listed  — written down by the organiser, not on the platform
            // invited — an existing club has been asked
            // accepted / declined — and has answered
            $table->string('state', 12)->default('listed');

            // Who was asked, and when they answered. The user id is the club's
            // owner at the moment of asking: an invitation is sent to a person,
            // and re-reading the owner later would silently re-address it.
            $table->unsignedBigInteger('invited_user_id')->nullable();
            $table->dateTime('invited_at')->nullable();
            $table->dateTime('responded_at')->nullable();

            // Filled in if this provisional club is ever turned into a real one.
            $table->unsignedBigInteger('promoted_tenant_id')->nullable();
            $table->dateTime('promoted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // A club can stand behind one event once. Two rows for the same
            // tenant would mean two invitations and two answers to reconcile.
            $table->unique(['event_id', 'tenant_id']);
            $table->index(['event_id', 'state']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_clubs');
    }
};

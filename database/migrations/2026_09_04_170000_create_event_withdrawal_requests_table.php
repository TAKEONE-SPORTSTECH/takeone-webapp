<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An athlete asks to be taken OUT of a competition.
 *
 * Why a request and not a delete
 * ------------------------------
 * `cancel()` has always refused outright — "registration is final" — so the
 * only way out of an entry list was to telephone the organiser. That is a real
 * gap (people get injured, and a name left in a bracket becomes a walkover
 * nobody planned), but self-service deletion is worse: a draw is CUT from the
 * entry list, so a competitor removing themselves an hour before the event
 * silently re-cuts somebody else's bracket, and after the first bout it would
 * corrupt a running competition.
 *
 * So the athlete raises a request and the organiser decides. Nobody leaves a
 * bracket without the person running it knowing.
 *
 * Shape borrowed deliberately from `event_public_entries`, which is the same
 * idea pointing the other way: a state machine in its own table, so the entry
 * list itself only ever holds people who are actually competing. The thirty-odd
 * places that read `club_event_registrations` as "who is competing" stay
 * correct without learning a new status.
 *
 * ONE open request per person per event, enforced by a unique index. A refused
 * request is REOPENED rather than duplicated (see App\Events\Support\Withdrawal)
 * — the same lesson `event_public_entries` taught in production, where a second
 * insert against its unique index met the athlete as a bare 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_withdrawal_requests', function (Blueprint $table) {
            $table->id();

            // The public key. Never the auto-increment id in a URL.
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // The entry this is about. Nullable on purpose: an organiser may
            // grant the request, which deletes the registration, and the record
            // of the decision must outlive the row it removed.
            $table->unsignedBigInteger('registration_id')->nullable();

            // pending → granted | refused | cancelled (withdrawn by the athlete)
            $table->string('state', 16)->default('pending');

            // Their words, shown to the organiser. Untrusted, escaped on output.
            $table->string('reason', 300)->nullable();

            // The organiser's answer, in their words.
            $table->string('response', 300)->nullable();

            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One person, one request per event — a double tap or a reload must
            // not put the same athlete in the organiser's queue twice.
            $table->unique(['event_id', 'user_id'], 'ewr_event_user_unq');

            // The organiser's queue reads "pending, this event, newest first".
            $table->index(['event_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_withdrawal_requests');
    }
};

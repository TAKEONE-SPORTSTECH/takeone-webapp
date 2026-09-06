<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Brazilian Jiu-Jitsu scoreboard's officiating LEDGER — package-owned.
 *
 * Owned by app/Events/Sports/BrazilianJiuJitsu/Tournament/Scoreboard, and named
 * for it so ownership stays obvious from database/migrations, where Laravel
 * insists on loading it from. Deleting the BJJ package means deleting that
 * folder, its registry line and these tables — nothing else.
 *
 * ── Why this table exists at all ────────────────────────────────────────────
 * Because the score is NOT stored. Every point, advantage and penalty is an
 * append-only row here, and the running score is DERIVED by replaying them
 * (App\Events\...\Scoreboard\Ledger). A correction never edits or deletes a
 * row: it appends a reversal that points at the row it undoes, with a reason
 * the operator had to type. That is what makes a jiu-jitsu score auditable
 * after the fact — "how did they get four points" is answerable from the
 * record rather than from somebody's memory of the mat.
 *
 * Nothing in here is ever UPDATEd or DELETEd by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bjj_match_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('event_id')->index();
            // Null only for a command that landed on a mat with no match on it.
            $table->unsignedBigInteger('match_id')->nullable()->index();
            $table->string('court', 40)->index();

            // Monotonic per (event, court), so the replay order never depends on
            // wall-clock timestamps two devices may disagree about.
            $table->unsignedInteger('sequence')->default(0);

            // What happened. A closed vocabulary enforced in the engine —
            // point, advantage, penalty, reverse, start, pause, end, …
            $table->string('action', 32);

            // Which corner, as a NEUTRAL side: 'a' is blue, 'b' is white, the
            // same mapping the draw's a_/b_ columns use. Null for a command
            // that belongs to neither corner (the clock, the bell).
            $table->string('side', 1)->nullable();

            // How much this was worth. Derived server-side from `source`, never
            // taken from the client.
            $table->unsignedTinyInteger('value')->default(0);

            // WHY it was worth that — takedown, sweep, knee_on_belly,
            // guard_pass, mount, back_control — or, for a penalty, its reason
            // (stalling, fleeing, …). This is the part a value alone cannot say
            // in jiu-jitsu, where two points is three different actions.
            $table->string('source', 32)->nullable();

            // A correction points at the row it undoes, and carries the reason
            // the operator was made to give. The replay skips any row named
            // here; neither row is ever removed.
            $table->unsignedBigInteger('reverses_id')->nullable()->index();
            $table->string('reason', 200)->nullable();

            // Who did it. Null when a paired scoring table acted on behalf of
            // the organiser who paired it — that organiser is resolved and
            // recorded, so this is only ever null for a genuinely unattributed
            // write, which the engine does not produce.
            $table->unsignedBigInteger('operator_id')->nullable()->index();

            // Where in the MATCH it happened, which is how a report cites it:
            // "guard pass to BLUE at 3:12", not "at 10:29:29".
            $table->decimal('clock_remaining', 8, 2)->nullable();
            $table->decimal('clock_duration', 8, 2)->nullable();

            // Anything else the command carried, capped by the engine.
            $table->json('payload')->nullable();

            // The SERVER's clock, never the caller's: a console with a drifted
            // clock must not be able to shift the timeline.
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            // The one query the replay makes.
            $table->index(['event_id', 'court', 'match_id', 'sequence'], 'bjj_events_replay_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bjj_match_events');
    }
};

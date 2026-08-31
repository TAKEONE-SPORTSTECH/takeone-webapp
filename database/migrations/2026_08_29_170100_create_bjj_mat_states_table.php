<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What one Brazilian Jiu-Jitsu mat is showing right now — package-owned.
 *
 * The sibling packages keep this in the cache, because their score is a
 * running counter worth nothing once the match ends. This one cannot: the
 * score here is DERIVED from bjj_match_events, so the state row holds only the
 * things a replay cannot answer — which match is loaded, what the screen is
 * showing, where the clock stands, what the two corners are announced as, and
 * the rules this mat is running under.
 *
 * A row per (event, court). Small, upserted, and durable: a wall screen that
 * reboots at 2pm comes back showing the match still happening in front of it,
 * and a cache flush mid-competition costs nothing.
 *
 * NOTHING about the RESULT lives here. A result becomes a fact through the
 * event type's recordOutcome(), exactly like every other result in the
 * product; this table is the scaffolding around a match in progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bjj_mat_states', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('event_id');
            $table->string('court', 40);

            // What the screen is showing: upcoming (the queue) · vs (the
            // introduction) · board (the match itself).
            $table->string('mode', 16)->default('upcoming');

            // Where the MATCH stands: idle · live · paused · review · medical ·
            // overtime · submission · dq · walkover · finished. WARNING is not
            // here on purpose — it is derived from the clock, so no two screens
            // can disagree about when the last minute started.
            $table->string('status', 16)->default('idle');

            $table->unsignedBigInteger('match_id')->nullable();
            $table->string('match_no', 16)->nullable();
            $table->string('stage', 40)->nullable();
            $table->string('division', 80)->nullable();
            $table->string('tournament', 100)->nullable();
            $table->string('court_label', 24)->nullable();
            $table->string('referee', 80)->nullable();
            $table->string('ruleset', 40)->nullable();

            // The two corners exactly as the screens announce them — assembled
            // once when a match is loaded, so the wall can never disagree with
            // the draw. Blue is side 'a', white is side 'b'.
            $table->json('blue')->nullable();
            $table->json('white')->nullable();

            // The clock, stored as "remaining as of a moment" plus whether it
            // is running. Every screen computes its own countdown from these,
            // so a screen joining late is instantly correct and two screens on
            // one mat cannot drift apart.
            $table->decimal('remaining', 8, 2)->default(300);
            $table->decimal('duration', 8, 2)->default(300);
            $table->boolean('running')->default(false);
            $table->timestamp('clock_at')->nullable();

            // The result the officials DECLARED, which outranks the score: a
            // submission, a disqualification, a walkover, a referee decision.
            $table->string('winner', 5)->nullable();          // 'blue' | 'white'
            $table->string('win_method', 24)->nullable();     // submission | points | decision | dq | walkover | medical | forfeit
            $table->string('win_note', 200)->nullable();

            // The match ended by itself (the bell, the fourth penalty) and
            // nobody has said HOW yet. The wall holds its celebration until an
            // official at the table answers.
            $table->boolean('awaiting_decision')->default(false);
            $table->boolean('celebration_closed')->default(false);

            // A stalling countdown the REFEREE is running. Private to the
            // console — the public board learns about it only if it is applied,
            // and then only as an ordinary penalty (see the design spec).
            $table->string('stall_side', 5)->nullable();
            $table->timestamp('stall_until')->nullable();

            // The last thing worth shouting about, for the board's 4s notice.
            $table->json('last_event')->nullable();

            // The rules this mat runs, so both consoles and the wall agree.
            $table->json('rules')->nullable();

            // Dark arena LED, or a bright venue / projector. One switch, never
            // per-colour edits.
            $table->string('theme', 16)->default('arena');

            $table->timestamps();

            // One state per mat, enforced by the database rather than by
            // convention: two rows for one mat is two truths in a hall.
            $table->unique(['event_id', 'court']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bjj_mat_states');
    }
};

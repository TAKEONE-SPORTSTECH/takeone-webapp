<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Mat — the two tables the casual scoreboard owns.
 *
 * Purely additive: nothing existing is altered, and dropping both would take
 * the feature away without touching a single row any other part of the app
 * reads (App\Events\OpenMat is deletable as one directory + one registry line).
 *
 * ── open_mat_corners ────────────────────────────────────────────────────────
 * Who is standing in each corner of a mat RIGHT NOW. Not a registration,
 * because a corner may be somebody with no account at all — the whole point of
 * the feature is that a stranger can be put on the mat by typing their name.
 * Corners outlive a bout on purpose: the pair fights, the result is filed, and
 * they are still standing there for the rematch until somebody changes them.
 *
 * ── open_mat_results ────────────────────────────────────────────────────────
 * What was fought, denormalised for the ONE question a casual record asks:
 * "how have I done, and against whom". The bout itself stays in event_matches
 * like every other bout in the product; this is the read model beside it, so a
 * member's record is one indexed query rather than a join across events,
 * registrations and matches for every profile view.
 *
 * A guest corner is a NULL user_id and a name — the name is kept so the other
 * side's record still reads "beat Marco" rather than "beat someone".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_mat_corners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('court', 40);
            // 'aka' (red) or 'ao' (blue) — the sport's own corner names, which
            // are what the scoring table and the wall screen already speak.
            $table->string('side', 8);

            // A platform member, or nobody. Nobody is not an error state here.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            // Two-letter ISO, for the flag on the board. Never a passport — the
            // country a corner is announced under is what the operator sets.
            $table->string('country', 2)->nullable();

            // How this corner came to be filled, kept for the audit trail a
            // scoreboard that anyone in the world can open deserves.
            //   picked  — searched for and placed by the operator
            //   joined  — took the corner themselves with the mat's code
            //   guest   — typed in, no account
            $table->string('source', 12)->default('guest');
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One person per corner, one corner per side.
            $table->unique(['event_id', 'court', 'side']);
            $table->index(['event_id', 'court']);
        });

        Schema::create('open_mat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            // The bout row this mirrors. Nullable so deleting a bout never
            // takes the record of it with it.
            $table->foreignId('match_id')->nullable()->constrained('event_matches')->nullOnDelete();
            $table->string('sport', 40);
            $table->string('court', 40)->nullable();

            $table->foreignId('a_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('b_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('a_name');
            $table->string('b_name');

            $table->string('winner', 4)->nullable();   // 'a' | 'b' | null (draw / no result)
            $table->unsignedInteger('a_score')->default(0);
            $table->unsignedInteger('b_score')->default(0);
            $table->string('win_reason', 40)->nullable();
            $table->string('win_note')->nullable();
            $table->timestamp('fought_at')->nullable();
            $table->timestamps();

            $table->index(['a_user_id', 'fought_at']);
            $table->index(['b_user_id', 'fought_at']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_mat_results');
        Schema::dropIfExists('open_mat_corners');
    }
};

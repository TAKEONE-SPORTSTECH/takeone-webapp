<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The FLOOR of an open mat: everybody who is here.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────
 *
 * The first cut had only two corners and no memory of anyone else, which read
 * well and worked badly: every new pair meant leaving the scoreboard, going
 * back to the console, finding both people again, and coming back. At an open
 * mat where eight people rotate through in an hour that is the whole evening
 * spent navigating.
 *
 * So the people accumulate. Somebody put in a corner joins the floor and stays
 * on it; the next pair is then two taps from a list that is already there,
 * made without leaving the scoring table. `bouts` is what makes the rotation
 * fair — the operator can see who has had three and who has had none.
 *
 * A person on the floor may be a member (`user_id`) or a typed guest (null).
 * Nothing downstream cares which: the board prints `name`, and `user_id`
 * decides only whether the bout lands on anybody's record.
 *
 * `open_mat_corners.person_id` now points here — a corner is a POSITION, and
 * this is the person standing in it. The name/user_id/country columns already
 * on the corner stay as a denormalised snapshot, exactly as `event_matches`
 * keeps `a_name` beside `a_competitor_id`, so a board never needs the join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_mat_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // A platform member, or nobody. Nobody is not an error state here.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('country', 2)->nullable();

            // picked — searched for and added by the operator
            // joined — took a corner themselves with the mat's code
            // guest  — typed in, no account
            $table->string('source', 12)->default('guest');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            // How many bouts they have had on this mat. The fairness check the
            // operator actually makes when choosing the next pair.
            $table->unsignedInteger('bouts')->default(0);
            $table->timestamp('last_bout_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'name']);
            // A member is on the floor once. Guests are not constrained: two
            // people called Marco is a real thing at an open mat.
            $table->unique(['event_id', 'user_id']);
        });

        Schema::table('open_mat_corners', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('event_id')
                ->constrained('open_mat_people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_mat_corners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('open_mat_people');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A league's own two tables — the competitors and the fixture list.
 *
 * Package-owned, because a league is not a bracket wearing different words:
 *
 *  · Its competitor is a TEAM. Everything else this platform runs is keyed to a
 *    person's entry (`club_event_registrations`); a five-a-side side is a name,
 *    a crest and possibly a club, and the people in it change from week to week.
 *  · Its fixture is not a bout. It has a matchday, a kick-off, a venue, two
 *    scores and — the thing no combat bout has — a DRAW.
 *  · Nothing advances. A knockout's whole engine is "the winner walks forward";
 *    a league's is a table, recomputed from every result each time it is read.
 *
 * So `event_matches` would have been the wrong shelf, and this is the shelf.
 * Delete `app/Events/League/`, these two tables and the one registry line and
 * leagues are gone with nothing else disturbed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_teams', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            $table->string('name', 80);
            $table->string('short_name', 12)->nullable();

            // The club this side represents, when it is one of ours. Null for a
            // team that exists only in this competition — a scratch side, a
            // school, a company team. `nullOnDelete` because a club leaving the
            // platform does not un-play their season.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('logo')->nullable();
            $table->string('country', 3)->nullable();

            // Withdrawn mid-season: their fixtures stay on the record (they were
            // played) and no new ones are cut for them.
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // One side per name per league. Two "Falcons" in one table is a
            // fixture list nobody can read.
            $table->unique(['event_id', 'name']);
        });

        Schema::create('league_fixtures', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Which round of the season. 1-based; a double round robin simply
            // keeps counting past the halfway point.
            $table->unsignedSmallInteger('matchday');

            $table->foreignId('home_team_id')->constrained('league_teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('league_teams')->cascadeOnDelete();

            $table->timestamp('kickoff_at')->nullable();
            $table->string('venue', 160)->nullable();

            // Null until it is played — which is NOT the same as 0-0, and the
            // table has to tell those apart or an unplayed fixture would count
            // as a draw for both sides.
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            // scheduled | played | postponed | cancelled
            $table->string('status', 12)->default('scheduled');

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'matchday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_fixtures');
        Schema::dropIfExists('league_teams');
    }
};

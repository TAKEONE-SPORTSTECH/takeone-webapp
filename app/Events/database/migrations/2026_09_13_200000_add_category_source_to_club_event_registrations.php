<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHO decided this entry's division — the platform, or a person.
 *
 * Both are legitimate, and they are not the same thing:
 *
 *  · `auto`   the entry gate or the weigh-in desk worked it out from gender,
 *             age, weight and belt. A guess the platform is responsible for,
 *             and may correct when the scale says something different.
 *
 *  · `manual` an organiser or a super-admin PUT them there — entering them
 *             into a named division, adding them to one from the division
 *             screen, or editing the entry. Weight classes are not only the
 *             federation's: an organiser runs the classes they choose and
 *             moves people between them for reasons this table does not hold
 *             (a bracket of three that needs a fourth, a strong junior who is
 *             safer up an age group, two clubs who travelled and must fight
 *             somebody). That decision is theirs and outranks the scale.
 *
 * Null on every row that predates this: unknown provenance, and treated as a
 * human's until proven otherwise — the conservative reading, because undoing
 * an organiser's deliberate exception is the expensive mistake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->string('category_source', 10)->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn('category_source');
        });
    }
};

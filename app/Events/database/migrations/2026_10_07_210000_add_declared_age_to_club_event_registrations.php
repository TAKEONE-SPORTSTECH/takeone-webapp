<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AGE, as the desk was told it, for an entrant whose date of birth nobody
 * knows (owner, 2026-10-07: "date of birth or age, selectable").
 *
 * Deliberately not turned into a birthdate: an invented birthdate looks
 * authoritative, drives age groups and the minor safeguards, and nobody ever
 * revisits it (CLAUDE.md → "Who Fills The Form Decides What It Demands"). The
 * age stays an age, on the entry it was given for.
 *
 * Additive; nullable; nothing existing reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('declared_age')->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropColumn('declared_age');
        });
    }
};

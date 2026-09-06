<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a division is FOR — the range an organiser groups by.
 *
 * A division has always been a name and a bag of entrants; who belongs in it
 * lived in the organiser's head and in the name they typed ("Adult Male White
 * −76 kg"). That is fine while a package cuts the divisions itself, and useless
 * the moment somebody builds one by hand: the picker has nothing to narrow by,
 * and nothing can say "this athlete is outside this group".
 *
 * So the range is recorded. Every column is NULLABLE and null means ANY — an
 * existing division keeps behaving exactly as it does today, and a division
 * that only cares about weight leaves the age columns alone.
 *
 * Deliberately NOT a gate. An organiser routinely puts a younger or lighter
 * athlete into a group they do not strictly belong to, to fill a bracket or
 * because the athlete can handle it. The range narrows the picker and flags the
 * exception; it never refuses one. See App\Events\Support\DivisionRange.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            // 'Male' | 'Female' | null. The canonical vocabulary (CLAUDE.md),
            // not m/f — this is compared against users.gender directly.
            $table->string('gender', 10)->nullable()->after('weight_class');

            // Age in whole years, inclusive at both ends.
            $table->unsignedSmallInteger('min_age')->nullable()->after('gender');
            $table->unsignedSmallInteger('max_age')->nullable()->after('min_age');

            // Kilograms, inclusive. Same precision as
            // club_event_registrations.weight, so a bound and a weigh-in can be
            // compared without either being rounded first.
            $table->decimal('min_weight', 5, 2)->nullable()->after('max_age');
            $table->decimal('max_weight', 5, 2)->nullable()->after('min_weight');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn(['gender', 'min_age', 'max_age', 'min_weight', 'max_weight']);
        });
    }
};

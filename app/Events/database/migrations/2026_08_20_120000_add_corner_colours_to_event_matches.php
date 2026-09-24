<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which corner each competitor actually fought in.
 *
 * Until now the corner was DERIVED: the scoreboards hand over "aka is 'a', ao is
 * 'b'" straight from the draw columns, and every screen coloured side a red and
 * side b blue on that assumption. It does not hold — in bout 12 of the National
 * Team Selection Trials the athlete in slot 'a' fought in BLUE, which the video
 * annotation on TAKEONE Play proves (match_points recorded blue 3, red 6, against
 * a scoresheet of 3 and 6 the other way round).
 *
 * Draw position and corner colour are simply different things: seeding decides
 * the slot, the mat decides the corner. So the colour becomes stored fact.
 *
 * Additive and nullable on purpose: NULL means "not recorded", and every reader
 * keeps falling back to the old a=red/b=blue assumption, so nothing that works
 * today changes until a corner is actually written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->string('a_corner', 4)->nullable()->after('a_provisional');
            $table->string('b_corner', 4)->nullable()->after('b_provisional');
        });
    }

    public function down(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropColumn(['a_corner', 'b_corner']);
        });
    }
};

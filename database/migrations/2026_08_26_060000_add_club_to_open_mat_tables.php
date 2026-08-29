<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The club a person on an open mat belongs to.
 *
 * The flag was already here and already right — it is the CLUB's country, never
 * the member's nationality, because at an event somebody competes for the club
 * that entered them. But the club ITSELF was thrown away the moment it was
 * consulted: `countryFor()` read `tenants.country` and kept only the two
 * letters, so a corner card could show a flag and not the name behind it.
 *
 * Stored on the row rather than resolved on read, for the same reason the name
 * and the country already are: a person's clubs can change during the season, a
 * guest has no account to resolve anything from, and an open mat that happened
 * in March should read in June exactly as it read on the night.
 *
 * Additive and nullable throughout — every existing row keeps working, and a
 * person with no club simply has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_mat_people', function (Blueprint $table) {
            $table->string('club')->nullable()->after('country');
        });

        Schema::table('open_mat_corners', function (Blueprint $table) {
            $table->string('club')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('open_mat_people', function (Blueprint $table) {
            $table->dropColumn('club');
        });

        Schema::table('open_mat_corners', function (Blueprint $table) {
            $table->dropColumn('club');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The competition's own timezone.
 *
 * Every date and time on an event (its days, start/end times, breaks, weigh-in,
 * entry window, late fee) is the HALL's wall clock. This names that clock.
 * NULL = the host's timezone (club or federation), then the platform default —
 * i.e. exactly what every existing event already means. Additive: nothing is
 * converted, every stored value keeps the wall time the organiser typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_events', 'timezone')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->string('timezone', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_events', 'timezone')) {
            Schema::table('club_events', fn (Blueprint $table) => $table->dropColumn('timezone'));
        }
    }
};

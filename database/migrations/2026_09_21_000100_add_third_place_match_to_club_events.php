<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether THIS championship plays third place off.
 *
 * The convention used to be the sport's alone — `bronzeRule()`, read once when
 * a draw was cut and never revisited — and the board's Actions menu offered a
 * "Third place" choice that wrote nothing at all, so an organiser selected it,
 * watched it revert on the next load, and had no way to say what their own
 * competition does.
 *
 * NULL means "whatever the sport says", which is every event that existed
 * before this column and every organiser who never opens the switch. A true or
 * a false is the organiser overriding it for their event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->boolean('third_place_match')->nullable()->after('sport');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('third_place_match');
        });
    }
};

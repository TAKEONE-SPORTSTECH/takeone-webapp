<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * May one person stand in more than one division's bracket at this event?
 * (owner, 2026-10-16 — a switch on the federation Draw tab and the draw desk.)
 *
 *   false — yes, as every event has always allowed (the default).
 *   true  — no: App\Events\Support\OneDivisionRule refuses to place a person
 *           in a second bracket.
 *
 * Additive, NOT NULL with a default of false: no existing row changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->boolean('one_division_per_person')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->dropColumn('one_division_per_person');
        });
    }
};

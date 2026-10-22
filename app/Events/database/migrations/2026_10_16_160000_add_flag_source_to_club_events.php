<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Which flag a competitor flies at this event (owner, 2026-10-16) —
 * App\Events\Support\FlagSource.
 *
 *   NULL / 'club'  — the country of the club they compete for, as every
 *                    event has always done.
 *   'nationality'  — their own nationality, the club's country when none
 *                    is on file.
 *
 * Additive and nullable: no existing row changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->string('flag_source', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->dropColumn('flag_source');
        });
    }
};

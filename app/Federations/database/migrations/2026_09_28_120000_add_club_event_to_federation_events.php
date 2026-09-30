<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A federation's competition RUNS as a platform event (2026-09-28): the
 * console, the draw, the hall screens, the scoreboard, the cameras, the
 * results — the same code a club's event uses, hosted by the federation
 * (club_events.host_type = 'federation'), never by a club. This is the link.
 *
 * No foreign key on purpose: the Events module must stay removable without
 * this table failing to migrate, and the other way round.
 *
 * Purely additive — one nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federation_events', function (Blueprint $t) {
            $t->unsignedBigInteger('club_event_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('federation_events', function (Blueprint $t) {
            $t->dropIndex(['club_event_id']);
            $t->dropColumn('club_event_id');
        });
    }
};

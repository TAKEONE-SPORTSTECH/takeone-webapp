<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where an event's divisions come from, as the organiser CHOSE it in the
 * auto-place popup (owner, 2026-10-15: "when I select something, stick to it").
 *
 *   'own'   — the organiser's own divisions, exactly as typed: every automatic
 *             placement fills those and nothing else, and creates nothing.
 *   'table' — a weight table (WKF, Shotokan…, the one in category_system).
 *   NULL    — never chosen: the platform decides as it always has
 *             (App\Events\Support\OwnDivisions::uses()).
 *
 * Additive and nullable: no existing row changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->string('division_source', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->dropColumn('division_source');
        });
    }
};

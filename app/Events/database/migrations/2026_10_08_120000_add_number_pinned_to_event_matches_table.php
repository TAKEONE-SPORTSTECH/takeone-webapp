<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bout number the ORGANISER chose (owner, 2026-10-08).
 *
 * Bout numbers are written by the scheduler, and the scheduler re-runs after
 * almost any change to a draw. A number an organiser or super-admin set by
 * hand — on the tablet, or by dragging on the day-order board — is marked
 * here, and the scheduler then numbers everything else AROUND it instead of
 * over it. Default false: every existing bout stays exactly as automatic as
 * it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->boolean('number_pinned')->default(false)->after('match_no');
        });
    }

    public function down(): void
    {
        Schema::table('event_matches', fn (Blueprint $table) => $table->dropColumn('number_pinned'));
    }
};

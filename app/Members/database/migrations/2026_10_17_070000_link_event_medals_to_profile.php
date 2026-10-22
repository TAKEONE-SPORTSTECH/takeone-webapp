<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A medal won at an event on this platform reaches the athlete's profile
 * (owner, 2026-10-17) — App\Members\Support\ProfileHistorySync::syncPodium().
 *
 *   tournament_events.club_event_id      — WHICH event a club-recorded
 *       tournament row stands for. Until now it was found again by its title,
 *       and an event renamed after entries opened (event 104: "Phase 1)" vs
 *       "Phase 1 )") would have been matched by nothing.
 *   performance_results.source_category_id — the division a medal row was
 *       written from. Only rows carrying it are ever replaced when a result
 *       changes; a medal the member typed in themselves never carries it.
 *
 * Additive and nullable: no existing row changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_events', function (Blueprint $t) {
            $t->unsignedBigInteger('club_event_id')->nullable()->index();
        });

        Schema::table('performance_results', function (Blueprint $t) {
            $t->unsignedBigInteger('source_category_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('performance_results', function (Blueprint $t) {
            $t->dropIndex(['source_category_id']);
            $t->dropColumn('source_category_id');
        });

        Schema::table('tournament_events', function (Blueprint $t) {
            $t->dropIndex(['club_event_id']);
            $t->dropColumn('club_event_id');
        });
    }
};

<?php

use App\Models\TournamentEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises the vouch table from tournament-only to any verifiable record
 * (tournament medals AND acquired skills) via a polymorphic `vouchable`.
 * Existing rows are backfilled to point at TournamentEvent.
 *
 * Written idempotently: each step is guarded so a partially-applied run (SQLite
 * can't drop a column still referenced by a foreign key without dropping the FK
 * first) can be completed by re-running.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('achievement_vouches', 'vouchable_type')) {
            Schema::table('achievement_vouches', fn (Blueprint $t) => $t->nullableMorphs('vouchable'));
        }

        if (Schema::hasColumn('achievement_vouches', 'tournament_event_id')) {
            // Backfill: every existing vouch belonged to a tournament.
            DB::table('achievement_vouches')->whereNull('vouchable_type')->update(['vouchable_type' => TournamentEvent::class]);
            DB::statement('UPDATE achievement_vouches SET vouchable_id = tournament_event_id WHERE vouchable_id IS NULL');

            // Drop the old unique index + FK first (both reference the column, and
            // SQLite refuses to drop a column still named by either), then the column.
            try {
                Schema::table('achievement_vouches', function (Blueprint $t) {
                    $t->dropUnique(['tournament_event_id', 'voucher_user_id']);
                });
            } catch (\Throwable $e) {
                // Index already dropped by a partial run — ignore.
            }
            Schema::table('achievement_vouches', function (Blueprint $t) {
                $t->dropForeign(['tournament_event_id']);
            });
            Schema::table('achievement_vouches', function (Blueprint $t) {
                $t->dropColumn('tournament_event_id');
            });
        }

        try {
            Schema::table('achievement_vouches', function (Blueprint $t) {
                $t->unique(['vouchable_type', 'vouchable_id', 'voucher_user_id']);
            });
        } catch (\Throwable $e) {
            // Unique index already present (re-run) — ignore.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('achievement_vouches', function (Blueprint $t) {
                $t->dropUnique(['vouchable_type', 'vouchable_id', 'voucher_user_id']);
            });
        } catch (\Throwable $e) {
        }

        if (! Schema::hasColumn('achievement_vouches', 'tournament_event_id')) {
            Schema::table('achievement_vouches', function (Blueprint $t) {
                $t->foreignId('tournament_event_id')->nullable();
            });
            DB::statement('UPDATE achievement_vouches SET tournament_event_id = vouchable_id');
        }

        if (Schema::hasColumn('achievement_vouches', 'vouchable_type')) {
            Schema::table('achievement_vouches', fn (Blueprint $t) => $t->dropMorphs('vouchable'));
        }
    }
};

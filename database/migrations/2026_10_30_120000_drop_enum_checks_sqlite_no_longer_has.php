<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL only — bring a schema built from migrations in line with the one
 * the platform actually ran on.
 *
 * On SQLite, every `->change()` rebuilds the table, and the rebuild drops the
 * CHECK constraint an `enum()` column was created with. These nine lost theirs
 * long ago, and the app has written values outside the original lists ever
 * since (`payment_status = 'pending_approval'`, among others). PostgreSQL keeps
 * the original checks, so a database built from migrations would refuse writes
 * the live platform makes every day. Validation lives in the app, as it has on
 * SQLite all along.
 *
 * `IF EXISTS` throughout: the production PostgreSQL schema was ported from the
 * SQLite one and already lacks them, so there this is a no-op.
 */
return new class extends Migration
{
    private const CHECKS = [
        'users' => ['users_blood_type_check', 'users_gender_check'],
        'memberships' => ['memberships_status_check'],
        'tournament_events' => ['tournament_events_type_check'],
        'skill_acquisitions' => ['skill_acquisitions_proficiency_level_check'],
        'club_member_subscriptions' => ['club_member_subscriptions_payment_status_check', 'club_member_subscriptions_status_check'],
        'club_transactions' => ['club_transactions_payment_method_check', 'club_transactions_type_check'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $check) {
                DB::statement(sprintf('ALTER TABLE "%s" DROP CONSTRAINT IF EXISTS "%s"', $table, $check));
            }
        }
    }

    public function down(): void
    {
        // Not restored: the app has written values outside the old lists.
    }
};

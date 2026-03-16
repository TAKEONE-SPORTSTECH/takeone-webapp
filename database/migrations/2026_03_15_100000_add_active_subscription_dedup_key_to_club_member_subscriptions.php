<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prevents duplicate active/pending subscriptions for the same
 * (tenant, user, package) combination.
 *
 * Strategy: a nullable "active_key" column holds a deterministic string
 * (tenant_id:user_id:package_id) while the subscription is active or
 * pending, and NULL for every other status. Because MySQL treats each
 * NULL as distinct in a unique index, expired/cancelled rows never
 * conflict with each other or with a future renewal — they are simply
 * invisible to the constraint.
 *
 * The column is maintained automatically by the application before
 * inserting or updating a subscription (see ClubMemberSubscription model).
 * This migration also backfills existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->string('active_key', 150)->nullable()->after('proof_of_payment');
            $table->unique('active_key', 'uq_active_subscription');
        });

        // Backfill existing active/pending rows using Eloquent (DB-agnostic)
        \App\Models\ClubMemberSubscription::whereIn('status', ['active', 'pending'])
            ->each(function ($sub) {
                $sub->update(['active_key' => "{$sub->tenant_id}:{$sub->user_id}:" . ($sub->package_id ?? 'null')]);
            });
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropUnique('uq_active_subscription');
            $table->dropColumn('active_key');
        });
    }
};

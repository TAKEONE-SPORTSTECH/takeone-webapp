<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'is_test_mode')) {
                $table->boolean('is_test_mode')->default(true)->after('status');
            }
        });

        foreach (['club_transactions', 'club_member_subscriptions', 'orders', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'is_test')) {
                    $blueprint->boolean('is_test')->default(false);
                }
            });
        }

        // Every club is currently used in test mode — mark all pre-existing
        // financial rows as test data so they surface in the review-and-purge
        // screen instead of silently mixing with future live data.
        DB::table('club_transactions')->update(['is_test' => true]);
        DB::table('club_member_subscriptions')->update(['is_test' => true]);
        DB::table('orders')->update(['is_test' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'is_test_mode')) {
                $table->dropColumn('is_test_mode');
            }
        });

        foreach (['club_transactions', 'club_member_subscriptions', 'orders', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'is_test')) {
                    $blueprint->dropColumn('is_test');
                }
            });
        }
    }
};

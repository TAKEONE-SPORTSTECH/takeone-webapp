<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on foreign-key columns that are filtered on hot paths but were
 * left unindexed (SQLite does not auto-index FK columns). Each was verified as
 * missing before adding — full table scans on family/membership lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_relationships', function (Blueprint $table) {
            $table->index('guardian_user_id');
            $table->index('dependent_user_id');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('club_transactions', function (Blueprint $table) {
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_relationships', function (Blueprint $table) {
            $table->dropIndex(['guardian_user_id']);
            $table->dropIndex(['dependent_user_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('club_transactions', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
        });
    }
};

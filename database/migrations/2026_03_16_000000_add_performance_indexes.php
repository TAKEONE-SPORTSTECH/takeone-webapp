<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'tenant_id'], 'idx_cms_user_tenant');
        });

        Schema::table('club_transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'transaction_date'], 'idx_ct_tenant_date');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->index(['tenant_id'], 'idx_ur_tenant');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'idx_memberships_tenant_status');
        });

        Schema::table('club_timeline_posts', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_ctp_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_cms_user_tenant');
        });

        Schema::table('club_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_ct_tenant_date');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropIndex('idx_ur_tenant');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('idx_memberships_tenant_status');
        });

        Schema::table('club_timeline_posts', function (Blueprint $table) {
            $table->dropIndex('idx_ctp_tenant_created');
        });
    }
};

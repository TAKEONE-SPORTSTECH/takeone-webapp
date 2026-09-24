<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a member's payment is approved by the club, record WHEN it was settled
 * so the member's payments page can show "Settled on <date>".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_member_subscriptions', 'settled_at')) {
            Schema::table('club_member_subscriptions', function (Blueprint $table) {
                $table->timestamp('settled_at')->nullable()->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_member_subscriptions', 'settled_at')) {
            Schema::table('club_member_subscriptions', function (Blueprint $table) {
                $table->dropColumn('settled_at');
            });
        }
    }
};

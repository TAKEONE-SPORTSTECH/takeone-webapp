<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            // Link subscription to club affiliation
            $table->foreignId('club_affiliation_id')->nullable()->after('user_id')->constrained('club_affiliations')->nullOnDelete();

            $table->index('club_affiliation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['club_affiliation_id']);
            $table->dropIndex(['club_affiliation_id']);
            $table->dropColumn('club_affiliation_id');
        });
    }
};

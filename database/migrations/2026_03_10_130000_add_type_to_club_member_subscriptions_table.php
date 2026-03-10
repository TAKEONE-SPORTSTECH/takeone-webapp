<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->string('type')->default('regular')->after('tenant_id');
        });

        // Backfill existing records
        DB::table('club_member_subscriptions')->update(['type' => 'regular']);
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

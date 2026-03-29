<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->string('refund_proof')->nullable()->after('proof_of_payment');
        });
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropColumn('refund_proof');
        });
    }
};

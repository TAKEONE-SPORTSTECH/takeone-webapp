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
            $table->string('proof_of_payment')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropColumn('proof_of_payment');
        });
    }
};

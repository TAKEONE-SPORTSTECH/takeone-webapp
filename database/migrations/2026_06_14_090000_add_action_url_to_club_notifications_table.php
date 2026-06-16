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
        Schema::table('club_notifications', function (Blueprint $table) {
            // Optional click-through target for the bell dropdown. When set, the
            // member is navigated here on selecting the notification (e.g. an
            // expired-subscription notice links to the bills page to renew).
            $table->string('action_url', 512)->nullable()->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_notifications', function (Blueprint $table) {
            $table->dropColumn('action_url');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive. The random code behind the QR on an official's badge (App\Events\Support\
 * EventBadges): the public verify page looks the appointment up by it, so it must be
 * unguessable — never the row id. NULL until the badge is first drawn. Removing the
 * official removes the row, and with it every badge they were ever given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->string('badge_token', 32)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropUnique(['badge_token']);
            $table->dropColumn('badge_token');
        });
    }
};

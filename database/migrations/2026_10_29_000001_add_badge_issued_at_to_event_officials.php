<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive. When an official's badge was ISSUED to them (App\Events\Support\EventBadges::
 * issue): they were notified and may open their own badge from then on. NULL means the
 * organiser has not sent it yet — the badge still exists for printing, as it always has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->timestamp('badge_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropColumn('badge_issued_at');
        });
    }
};

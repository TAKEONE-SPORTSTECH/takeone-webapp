<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listed or hidden (owner, 2026-10-04): a HIDDEN event appears in no list on
 * the platform — Explore, the club's page, the federation's dashboard, the
 * spotlight, announcements — and is reached only by its link, which anybody
 * holding it may open (an "unlisted" event). `listed` is what every event has
 * always been. Additive: one column with a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_events', 'visibility')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->string('visibility', 10)->default('listed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_events', 'visibility')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->dropColumn('visibility');
            });
        }
    }
};

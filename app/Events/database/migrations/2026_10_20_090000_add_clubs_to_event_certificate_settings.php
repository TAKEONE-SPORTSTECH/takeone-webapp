<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clubs' thank-you certificates — one for every club that took part (owner,
 * 2026-10-20). Off by default: an event issues them only once its organiser
 * switches them on. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_certificate_settings', 'clubs')) {
            Schema::table('event_certificate_settings', function (Blueprint $t) {
                $t->boolean('clubs')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_certificate_settings', 'clubs')) {
            Schema::table('event_certificate_settings', fn (Blueprint $t) => $t->dropColumn('clubs'));
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Officials' certificates — a thank-you for helping make the event a success
 * (owner, 2026-10-15). Off by default: an event issues them only once its
 * organiser switches them on. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_certificate_settings', 'officials')) {
            Schema::table('event_certificate_settings', function (Blueprint $t) {
                $t->boolean('officials')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_certificate_settings', 'officials')) {
            Schema::table('event_certificate_settings', fn (Blueprint $t) => $t->dropColumn('officials'));
        }
    }
};

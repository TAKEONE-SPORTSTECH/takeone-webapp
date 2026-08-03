<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-bout call notifications key the ledger as `call:{bout}:{entry}:{threshold}`,
 * which outgrows the original 40-character milestone column once ids get long.
 * SQLite ignores the length, but MySQL would truncate — and a truncated key
 * collides, which would silently swallow a call to the mat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_notifications_sent', function (Blueprint $table) {
            $table->string('milestone', 120)->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_notifications_sent', function (Blueprint $table) {
            $table->string('milestone', 40)->change();
        });
    }
};

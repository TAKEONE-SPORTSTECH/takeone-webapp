<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A screen exists before it knows which mat it is.
 *
 * A screen is unboxed, powered on, and stands there showing a QR code until an
 * organiser tells it what it is looking at. So the event and the court become
 * nullable, and the row gains the pairing code that the QR carries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_displays', function (Blueprint $table) {
            // Short, human-readable, and shown on a screen in a public hall —
            // so it is worthless on its own: claiming with it requires an
            // organiser who can already manage the event.
            $table->string('pairing_code', 12)->nullable()->unique()->after('token_hint');
            $table->timestamp('claimed_at')->nullable()->after('last_seen_at');
        });

        // SQLite cannot ALTER a column to nullable in place — rebuild the two.
        Schema::table('court_displays', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->change();
            $table->string('court', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('court_displays', function (Blueprint $table) {
            $table->dropColumn(['pairing_code', 'claimed_at']);
        });
    }
};

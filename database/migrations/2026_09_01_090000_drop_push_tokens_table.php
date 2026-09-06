<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firebase Cloud Messaging is gone; this held its per-device registration
 * tokens.
 *
 * Safe to drop rather than leave behind, because it never held anything: the
 * Android app has no Firebase SDK and therefore never minted a token, so the
 * table sat at zero rows from the day it was created (verified 2026-09-01,
 * immediately before this migration, with a fresh backup taken first). Native
 * push is delivered over MQTT by the broker the rest of the platform already
 * uses — the app holds one connection in a foreground service and posts what
 * arrives, so there is no token to register and nothing to store here.
 *
 * `down()` recreates the table exactly as it was, so the drop is reversible
 * even though what it stored is not (and never existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('push_tokens');
    }

    public function down(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('platform', 20)->default('android');
            $table->timestamps();

            $table->unique('token');
            $table->index(['user_id', 'platform']);
        });
    }
};

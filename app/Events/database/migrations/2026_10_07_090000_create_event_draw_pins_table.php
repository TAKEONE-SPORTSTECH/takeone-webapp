<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PIN an organiser sets so a scoring TABLET can edit the draw in an
 * emergency — arrange it, admit a late entrant (owner, 2026-10-07).
 *
 * One row per event. The PIN is never stored, only its hash. `user_id` is the
 * organiser who set it: an edit made on a tablet with this PIN is made WITH
 * that person's authority and recorded against them — the tablet itself is a
 * device and grants nothing. Failed attempts are counted here so a lock-out
 * survives the tablet being restarted.
 *
 * Additive: a new table, nothing else touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_draw_pins')) {
            return;
        }

        Schema::create('event_draw_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pin_hash');
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_draw_pins');
    }
};

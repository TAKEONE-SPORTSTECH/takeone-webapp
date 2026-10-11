<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Side exhibition bouts — any two entrants of an event, fought on a mat
 * OUTSIDE the draw (owner, 2026-10-09: the scoring console's Arcade tab).
 *
 * Deliberately its own table and never a row in `event_matches`: nothing that
 * reads the draw — brackets, advancement, podiums, running order, W–L records,
 * certificates — can ever see one. Kept inside the event so the organiser has
 * the record of what was fought.
 *
 * Additive: a new table, nothing else touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_exhibition_bouts')) {
            return;
        }

        Schema::create('event_exhibition_bouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('court', 40);
            // The two ENTRIES (registrations) of this event. Null-on-delete so
            // a withdrawn entry leaves the record readable by its names.
            $table->foreignId('a_registration_id')->nullable()->constrained('club_event_registrations')->nullOnDelete();
            $table->foreignId('b_registration_id')->nullable()->constrained('club_event_registrations')->nullOnDelete();
            $table->string('a_name')->nullable();
            $table->string('b_name')->nullable();
            $table->string('a_score', 12)->nullable();
            $table->string('b_score', 12)->nullable();
            $table->string('winner', 1)->nullable();          // 'a' | 'b'
            $table->string('win_reason', 20)->nullable();
            $table->string('win_note', 200)->nullable();
            $table->string('status', 12)->default('running'); // running | done | abandoned
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finished_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'number']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_exhibition_bouts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Officials appointed to a single event — the jury.
 *
 * Per event, not a standing role: a jury is appointed for a given championship
 * and has no authority over the next one. That is why this is a link table
 * rather than a row in `roles`.
 *
 * Being an official grants exactly one thing today — arranging the draw before
 * the event starts. It deliberately does NOT grant canManage(), which covers
 * editing, deleting, results and financials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Room for referees/recorders later without another table.
            $table->string('role')->default('jury');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One appointment per person per event.
            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_officials');
    }
};

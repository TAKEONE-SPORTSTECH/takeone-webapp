<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations: an event's schedule — the Schedule tab of the dashboard.
 *
 * The tab used to draw a fixed demo programme ("Day 1 — Eliminations",
 * "Grand ballroom") on every event. Now each event keeps its own: DAYS, in
 * order, each with an optional title, and on each day the SESSIONS the staff
 * type in (a clock time, what happens, where). A day's number is its place
 * in the order, never stored, so deleting Day 2 makes Day 3 the new Day 2.
 *
 * Both rows are addressed by uuid from the page (never the numeric id).
 * Purely additive — two new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_event_id')->constrained('federation_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title', 120)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['federation_event_id', 'position']);
        });

        Schema::create('federation_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_schedule_day_id')->constrained('federation_schedule_days')->cascadeOnDelete();
            $table->string('starts_at', 5);          // HH:MM
            $table->string('ends_at', 5)->nullable(); // HH:MM
            $table->string('title', 160);
            $table->string('location', 160)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['federation_schedule_day_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_schedule_slots');
        Schema::dropIfExists('federation_schedule_days');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organiser's run-day checklist: what must be true before the day begins.
 *
 * Deliberately NOT owned by an event-type package. "Mats laid, first-aid on
 * site, scoreboard tested" is not a Taekwondo fact — every kind of event has a
 * version of it, and the items are free text the organiser writes. A package
 * that wants to contribute its own required items can add rows here; it does
 * not need a table of its own.
 *
 * Each item records WHO cleared it and WHEN, because that is the whole point:
 * an unsigned checklist is a to-do list, a signed one is a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamps();

            // The two reads this table gets: draw one event's list in order,
            // and count what is still outstanding.
            $table->index(['event_id', 'sort_order']);
            $table->index(['event_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_checklist_items');
    }
};

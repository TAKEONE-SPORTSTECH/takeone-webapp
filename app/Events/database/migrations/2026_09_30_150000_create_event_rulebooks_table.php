<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which rule-book VERSION an event runs under (Sport Package Plan, phase 4).
 *
 * Additive. An event with no row is locked to the newest version that had been
 * published when the event was created — for every event that exists today,
 * that is version 1, i.e. exactly the rules it has always run under. A row is
 * written only when an organiser deliberately chooses another version.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_rulebooks')) {
            return;
        }

        Schema::create('event_rulebooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->unique()->constrained('club_events')->cascadeOnDelete();
            $t->string('edition', 40);
            $t->foreignId('chosen_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rulebooks');
    }
};

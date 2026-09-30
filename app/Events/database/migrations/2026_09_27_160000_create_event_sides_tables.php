<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pairs and teams as entrants — step 3 of the generic-sports plan (2026-09-27).
 *
 * Until now every entrant was ONE person: `club_event_registrations` is one row
 * per person per division, and bouts point at those rows. A padel pair, a
 * football squad, a relay team or a team kata is several people entered as one.
 *
 * ADDITIVE ONLY (RULE #1). Nothing that exists is altered: a person still has
 * their own registration (their fee, weigh-in, photo and entry state live
 * there), and a SIDE groups those registrations under one name. Bouts, the draw
 * and results are untouched; pointing a contest at sides is a later step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            // The division the side is entered in; null while it is still being put together.
            $table->foreignId('category_id')->nullable()->constrained('event_categories')->nullOnDelete();
            $table->string('kind', 8);                      // 'pair' | 'team'
            $table->string('name', 80)->nullable();         // a pair is named from its players when blank
            $table->string('short_name', 12)->nullable();
            // The club it represents, where it represents one.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->unsignedSmallInteger('seed')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'category_id']);
        });

        Schema::create('event_side_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('side_id')->constrained('event_sides')->cascadeOnDelete();
            // The person's own entry in the event — fee, weigh-in, photo stay there.
            $table->foreignId('registration_id')->constrained('club_event_registrations')->cascadeOnDelete();
            $table->string('role', 12)->default('player');  // player | substitute | captain | coach
            $table->unsignedSmallInteger('number')->nullable(); // shirt / bib number
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['side_id', 'registration_id']);
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_side_members');
        Schema::dropIfExists('event_sides');
    }
};

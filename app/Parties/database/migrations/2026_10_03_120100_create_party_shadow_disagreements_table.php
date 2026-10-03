<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where App\Parties\Authority (shadow mode) and the old permission check gave
 * different answers (PLAN.md Phase 4). One row per (check, user, subject) —
 * repeats bump `hits`, so a busy page cannot flood it. A module is cut over
 * only when its rows are zero. Additive; dropped once the cut-over is done.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('party_shadow_disagreements')) {
            return;
        }

        Schema::create('party_shadow_disagreements', function (Blueprint $table) {
            $table->id();
            $table->string('check', 60);
            $table->unsignedBigInteger('user_id');
            $table->string('subject', 80);         // e.g. "club:12"
            $table->boolean('old_answer');
            $table->boolean('new_answer');
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unique(['check', 'user_id', 'subject'], 'party_shadow_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_shadow_disagreements');
    }
};

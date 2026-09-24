<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member-authored personal training sessions shown on /me/schedule alongside
 * the read-only sessions synced from enrolled club packages. Stores every field
 * the schedule card + detail render, so a personal session looks identical to a
 * club one. Weekly-recurring: a session is pinned to a day-of-week + time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_schedule_sessions', function (Blueprint $table) {
            $table->id();
            // Owner who created/manages the entry.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Who the session is FOR — self or one of the owner's dependents.
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('day', 12);                 // sunday..saturday
            $table->string('start_time', 12)->nullable(); // e.g. "6:30 AM"
            $table->string('end_time', 12)->nullable();

            $table->string('title');
            $table->string('discipline')->nullable();
            $table->string('icon', 40)->default('bi-calendar-check');
            $table->string('color', 9)->default('#7c3aed');
            $table->string('coach')->nullable();
            $table->string('location')->nullable();
            $table->string('intensity', 20)->nullable(); // Low | Moderate | High

            $table->json('focus')->nullable();   // ["Legs","Core",...]
            $table->text('notes')->nullable();
            $table->json('workout')->nullable(); // {warmup:[],main:[{name,sets,reps,note}],cooldown:[]}

            $table->timestamps();

            $table->index(['user_id', 'day']);
            $table->index('subject_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_schedule_sessions');
    }
};

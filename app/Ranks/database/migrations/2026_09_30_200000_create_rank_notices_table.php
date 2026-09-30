<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which rank reminders have already been sent — so each one goes exactly once
 * ("ready for grading" once per belt, "grading tomorrow" once per grading).
 * Additive; holds no content, only that a notice went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rank_notices')) {
            return;
        }
        Schema::create('rank_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $t->string('kind', 24);
            $t->string('ref', 80);
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'activity_id', 'kind', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_notices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-time training-program variation for a single dated occurrence of a
 * club class (a weekly schedule slot on a package activity). Lets the coach
 * or club owner change intensity/focus/notes/workout content for just that
 * date without touching the recurring plan stored on
 * club_package_activities.schedule — the following week automatically reverts
 * to the recurring default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_program_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);
            $table->string('slot_start', 12)->nullable();
            $table->date('date');

            $table->string('intensity', 20)->nullable();
            $table->json('focus')->nullable();
            $table->text('notes')->nullable();
            $table->json('workout')->nullable();

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'date'], 'class_program_override_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_program_overrides');
    }
};

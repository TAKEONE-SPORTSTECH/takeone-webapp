<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One rating (1–5 ★ + optional comment) per trainee per club class — about the class itself, separate from the trainer rating. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);
            $table->string('slot_start', 12)->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'user_id'], 'class_rating_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('class_ratings'); }
};

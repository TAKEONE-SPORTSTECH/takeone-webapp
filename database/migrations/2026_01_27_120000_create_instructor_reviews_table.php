<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instructor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('club_instructors')->onDelete('cascade');
            $table->foreignId('reviewer_user_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned()->comment('1-5 stars');
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            // Ensure one review per user per instructor
            $table->unique(['instructor_id', 'reviewer_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructor_reviews');
    }
};

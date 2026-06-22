<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Direct "this instructor teaches these classes (activities)" link, independent of
     * any package. The Packages builder's per-activity instructor picker stays as-is and
     * is mirrored into this table so both places stay in sync.
     */
    public function up(): void
    {
        Schema::create('club_activity_instructor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('club_instructors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'instructor_id']);
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_activity_instructor');
    }
};

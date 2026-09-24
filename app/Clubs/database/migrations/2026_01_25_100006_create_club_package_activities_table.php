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
        Schema::create('club_package_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('club_packages')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('club_instructors')->nullOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'activity_id']);
            $table->index('package_id');
            $table->index('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_package_activities');
    }
};

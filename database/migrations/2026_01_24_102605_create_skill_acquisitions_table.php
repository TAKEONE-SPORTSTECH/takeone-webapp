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
        Schema::create('skill_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_affiliation_id')->constrained('club_affiliations')->onDelete('cascade');
            $table->string('skill_name');
            $table->string('icon')->nullable(); // FontAwesome or Heroicons class
            $table->integer('duration_months');
            $table->enum('proficiency_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('beginner');
            $table->timestamps();

            $table->index(['club_affiliation_id', 'skill_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_acquisitions');
    }
};

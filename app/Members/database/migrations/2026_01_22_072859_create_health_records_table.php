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
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('recorded_at');
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->decimal('body_water_percentage', 5, 2)->nullable();
            $table->decimal('muscle_mass', 5, 2)->nullable();
            $table->decimal('bone_mass', 5, 2)->nullable();
            $table->integer('visceral_fat')->nullable();
            $table->integer('bmr')->nullable();
            $table->decimal('protein_percentage', 5, 2)->nullable();
            $table->integer('body_age')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};

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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('target_date');
            $table->decimal('current_progress_value', 8, 2)->default(0);
            $table->decimal('target_value', 8, 2);
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->enum('priority_level', ['high', 'medium', 'low'])->default('medium');
            $table->string('unit')->nullable(); // e.g., lbs, min, kg
            $table->string('icon_type')->default('target'); // e.g., target, dumbbell, clock
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};

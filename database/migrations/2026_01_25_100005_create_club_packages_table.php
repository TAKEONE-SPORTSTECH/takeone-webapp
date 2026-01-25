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
        Schema::create('club_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // e.g., "Juniors & Fighters (S.M.W) Group A"
            $table->string('cover_image')->nullable();
            $table->enum('type', ['single', 'multi'])->default('single'); // Single Activity or Multi Activity
            $table->integer('age_min')->nullable(); // Minimum age
            $table->integer('age_max')->nullable(); // Maximum age
            $table->enum('gender', ['mixed', 'male', 'female'])->default('mixed');
            $table->decimal('price', 10, 2); // Price in club's currency
            $table->integer('duration_months')->default(1); // Package duration in months
            $table->integer('session_count')->default(0); // Total sessions included (0 = unlimited)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_packages');
    }
};

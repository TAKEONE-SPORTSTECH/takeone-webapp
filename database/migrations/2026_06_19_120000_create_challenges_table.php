<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solo / club challenges — a member-facing goal (e.g. "10K steps a day")
     * that any member can join and log progress toward. Created per club.
     */
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('tag')->nullable();                 // e.g. Cardio, Strength
            $table->string('category')->default('fitness');
            $table->string('icon')->default('bi-flag');
            $table->string('color', 9)->default('#7c3aed');
            $table->string('metric')->default('points');       // steps, sessions, seconds…
            $table->unsignedInteger('goal')->default(0);       // target value (0 = open)
            $table->string('unit')->nullable();                // s, kg, '' …
            $table->unsignedInteger('points')->default(100);   // reward points on completion
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->text('about')->nullable();
            $table->json('rules')->nullable();
            $table->json('rewards')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};

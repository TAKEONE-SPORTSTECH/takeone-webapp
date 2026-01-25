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
        Schema::create('club_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // e.g., "TAEKWONDO (S.M.W) CLASS A"
            $table->integer('duration_minutes')->default(60);
            $table->integer('frequency_per_week')->default(3); // How many times per week
            $table->foreignId('facility_id')->nullable()->constrained('club_facilities')->nullOnDelete();
            $table->json('schedule')->nullable();
            // Example schedule structure:
            // [
            //   {"day": "Saturday", "time": "16:00"},
            //   {"day": "Monday", "time": "16:00"},
            //   {"day": "Wednesday", "time": "16:00"}
            // ]
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('facility_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_activities');
    }
};

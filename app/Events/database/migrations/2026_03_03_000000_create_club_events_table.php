<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('level')->nullable();          // e.g. "Intermediate & Above", "Ages 5+"
            $table->unsignedInteger('max_capacity')->nullable();
            $table->unsignedInteger('spots_taken')->default(0);
            $table->string('ribbon_label')->nullable();   // e.g. "Limited Seats", "Family Friendly"
            $table->string('ribbon_type')->nullable();    // 'limited' = red style, null = default green
            $table->json('tags')->nullable();             // ["Public event", "WT rules"]
            $table->string('color', 20)->nullable();      // hex for date pill, e.g. "#16a34a"
            $table->string('cta_text')->nullable();       // "Join Event", "I'm Interested"
            $table->string('status')->default('active');  // active | cancelled | completed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_events');
    }
};

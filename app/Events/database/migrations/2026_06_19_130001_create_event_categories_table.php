<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tournament weight/skill categories — each holds its own bracket. */
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('name');                         // "Men −58 kg"
            $table->string('weight_class')->nullable();     // "Fin weight"
            $table->unsignedInteger('capacity')->default(8);
            $table->string('status')->default('enrolling'); // enrolling | live | completed
            $table->string('note')->nullable();
            $table->json('podium')->nullable();             // [{place,name,country,prize}]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_categories');
    }
};

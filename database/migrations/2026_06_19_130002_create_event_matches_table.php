<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A single bracket bout within a category (stored flat, grouped by round). */
    public function up(): void
    {
        Schema::create('event_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->string('round');                 // "Quarter-finals" | "Semi-finals" | "Final"
            $table->unsignedInteger('slot')->default(0);

            $table->string('a_name')->nullable();
            $table->string('a_country', 8)->nullable();
            $table->unsignedInteger('a_seed')->nullable();
            $table->string('a_score', 16)->nullable();

            $table->string('b_name')->nullable();
            $table->string('b_country', 8)->nullable();
            $table->unsignedInteger('b_seed')->nullable();
            $table->string('b_score', 16)->nullable();

            $table->string('winner')->nullable();    // 'a' | 'b' | null
            $table->string('court')->nullable();
            $table->string('scheduled_time')->nullable();
            $table->string('status')->default('upcoming'); // upcoming | live | done
            $table->timestamps();

            $table->index(['category_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_matches');
    }
};

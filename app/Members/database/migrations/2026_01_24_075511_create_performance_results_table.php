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
        Schema::create('performance_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_event_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->enum('medal_type', ['1st', '2nd', '3rd', 'special']);
            $table->integer('points')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_results');
    }
};

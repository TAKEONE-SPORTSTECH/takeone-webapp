<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each event's ranking coefficient (the WKF "event factor"): what one event's
 * results are multiplied by in the national ranking. No row = the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('club_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('factor');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_events');
    }
};

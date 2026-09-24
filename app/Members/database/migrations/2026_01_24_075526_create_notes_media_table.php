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
        Schema::create('notes_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_event_id')->constrained()->onDelete('cascade');
            $table->text('note_text')->nullable();
            $table->string('media_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes_media');
    }
};

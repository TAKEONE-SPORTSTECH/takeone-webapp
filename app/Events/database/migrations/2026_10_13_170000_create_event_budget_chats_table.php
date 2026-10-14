<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The budget advisor's MEMORY — one running conversation per organiser per
 * event, kept on the server like an agent's chat memory, so it survives a
 * closed sheet or a reloaded page. A window of recent turns only; "New chat"
 * empties it. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_budget_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->json('messages')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_budget_chats');
    }
};

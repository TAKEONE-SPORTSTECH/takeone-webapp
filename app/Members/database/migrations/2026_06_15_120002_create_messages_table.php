<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // Encrypted-at-rest: the Message model casts `body` with Laravel's
            // `encrypted` cast (AES-256-GCM via APP_KEY). A DB dump is unreadable;
            // the server can still decrypt for moderation/previews. Ciphertext is
            // much longer than plaintext, so this is TEXT, not a string column.
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

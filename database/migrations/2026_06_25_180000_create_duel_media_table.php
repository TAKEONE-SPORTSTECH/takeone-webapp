<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duel_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duel_id')->constrained('duels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10);            // image | video | link
            $table->string('url', 1000);           // storage path (image/video) or external URL (link)
            $table->string('caption')->nullable();
            $table->timestamps();
            $table->index('duel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_media');
    }
};

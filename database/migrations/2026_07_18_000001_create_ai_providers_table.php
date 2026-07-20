<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI provider registry — the "add your AI APIs" settings surface. Each row is a
 * configured provider for one modality (text / tts / stt / image). API keys are
 * stored encrypted (see AiProvider::$casts) and never returned to the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // human label
            $table->string('modality');                   // text | tts | stt | image
            $table->string('driver');                     // ollama | openai | anthropic | elevenlabs | automatic1111 | whisper | ...
            $table->string('base_url')->nullable();       // for local/self-hosted or custom endpoints
            $table->text('api_key')->nullable();          // encrypted at rest
            $table->string('model')->nullable();
            $table->json('options')->nullable();          // driver-specific extras (temperature, voice_id, size…)
            $table->boolean('is_default')->default(false); // default provider for its modality
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['modality', 'enabled', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};

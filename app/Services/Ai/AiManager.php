<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use App\Services\Ai\Contracts\ImageDriver;
use App\Services\Ai\Contracts\SttDriver;
use App\Services\Ai\Contracts\TextDriver;
use App\Services\Ai\Contracts\TtsDriver;
use App\Services\Ai\Drivers\AnthropicTextDriver;
use App\Services\Ai\Drivers\GeminiImageDriver;
use App\Services\Ai\Drivers\GeminiSttDriver;
use App\Services\Ai\Drivers\GeminiTextDriver;
use App\Services\Ai\Drivers\GeminiTtsDriver;
use App\Services\Ai\Drivers\OllamaTextDriver;
use App\Services\Ai\Drivers\OpenAiImageDriver;
use App\Services\Ai\Drivers\OpenAiTextDriver;

/**
 * Resolves the configured AI provider for a modality into a concrete driver.
 * Text falls back to the built-in Ollama (config/copilot.php) when no provider
 * row is configured, so the assistant works out of the box with zero setup.
 *
 * Voice (tts/stt) and image drivers are registered in settings now but wired in
 * their own phases — makeTextDriver throws a clear error for unknown drivers.
 */
class AiManager
{
    /** Resolve a text driver: a specific provider id, else the default, else built-in Ollama. */
    public function text(?int $providerId = null): TextDriver
    {
        $provider = $providerId
            ? AiProvider::query()->enabled()->find($providerId)
            : $this->defaultProvider('text');

        return $provider ? $this->makeTextDriver($provider) : $this->fallbackOllama();
    }

    /**
     * Resolve an image driver from the configured image provider.
     * Throws if none is configured (image generation has no built-in fallback).
     */
    public function image(?int $providerId = null): ImageDriver
    {
        $provider = $providerId
            ? AiProvider::query()->enabled()->find($providerId)
            : $this->defaultProvider('image');

        if (! $provider) {
            throw new \RuntimeException('No image provider is configured. Add one under Admin → AI Providers (modality: image).');
        }

        $o = $provider->options ?? [];

        return match ($provider->driver) {
            'openai' => new OpenAiImageDriver(
                $provider->base_url ?: 'https://api.openai.com/v1',
                (string) $provider->api_key,
                $provider->model ?: 'gpt-image-1',
                (string) ($o['size'] ?? '1536x1024'),
                (int) ($o['timeout'] ?? 180),
            ),
            'gemini' => new GeminiImageDriver(
                $provider->base_url ?: 'https://generativelanguage.googleapis.com/v1beta',
                (string) $provider->api_key,
                $provider->model ?: 'gemini-2.5-flash-image',
                (int) ($o['timeout'] ?? 180),
            ),
            default => throw new \RuntimeException("Image driver [{$provider->driver}] is not available yet."),
        };
    }

    /** Resolve a text-to-speech driver from the configured tts provider. */
    public function tts(?int $providerId = null): TtsDriver
    {
        $p = $providerId ? AiProvider::query()->enabled()->find($providerId) : $this->defaultProvider('tts');

        if (! $p) {
            throw new \RuntimeException('No voice (TTS) provider is configured. Add one under Admin → AI Providers (modality: tts).');
        }

        $o = $p->options ?? [];

        return match ($p->driver) {
            'gemini' => new GeminiTtsDriver(
                $p->base_url ?: 'https://generativelanguage.googleapis.com/v1beta',
                (string) $p->api_key,
                $p->model ?: 'gemini-2.5-flash-preview-tts',
                (string) ($o['voice'] ?? 'Kore'),
                (int) ($o['timeout'] ?? 120),
            ),
            default => throw new \RuntimeException("TTS driver [{$p->driver}] is not available yet."),
        };
    }

    /** Resolve a speech-to-text driver from the configured stt provider. */
    public function stt(?int $providerId = null): SttDriver
    {
        $p = $providerId ? AiProvider::query()->enabled()->find($providerId) : $this->defaultProvider('stt');

        if (! $p) {
            throw new \RuntimeException('No voice (STT) provider is configured. Add one under Admin → AI Providers (modality: stt).');
        }

        $o = $p->options ?? [];

        return match ($p->driver) {
            'gemini' => new GeminiSttDriver(
                $p->base_url ?: 'https://generativelanguage.googleapis.com/v1beta',
                (string) $p->api_key,
                $p->model ?: 'gemini-2.5-flash',
                (int) ($o['timeout'] ?? 120),
            ),
            default => throw new \RuntimeException("STT driver [{$p->driver}] is not available yet."),
        };
    }

    /** The default (or first enabled) provider for a modality, or null. */
    public function defaultProvider(string $modality): ?AiProvider
    {
        return AiProvider::query()->enabled()->modality($modality)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /** The built-in local Ollama text driver, exposed as a resilience fallback. */
    public function textFallback(): TextDriver
    {
        return $this->fallbackOllama();
    }

    /** True when a hosted text provider is configured (i.e. text() != the Ollama fallback). */
    public function hasHostedTextProvider(): bool
    {
        return (bool) $this->defaultProvider('text');
    }

    private function fallbackOllama(): TextDriver
    {
        return new OllamaTextDriver(
            (string) config('copilot.base_url'),
            (string) config('copilot.model'),
            (float) config('copilot.temperature', 0.2),
            (int) config('copilot.timeout', 120),
        );
    }

    private function makeTextDriver(AiProvider $p): TextDriver
    {
        $o = $p->options ?? [];

        return match ($p->driver) {
            'ollama' => new OllamaTextDriver(
                $p->base_url ?: (string) config('copilot.base_url'),
                $p->model ?: (string) config('copilot.model'),
                (float) ($o['temperature'] ?? 0.2),
                (int) ($o['timeout'] ?? 120),
            ),
            'openai' => new OpenAiTextDriver(
                $p->base_url ?: 'https://api.openai.com/v1',
                (string) $p->api_key,
                $p->model ?: 'gpt-4o-mini',
                (float) ($o['temperature'] ?? 0.2),
                (int) ($o['timeout'] ?? 120),
            ),
            'anthropic' => new AnthropicTextDriver(
                $p->base_url ?: 'https://api.anthropic.com',
                (string) $p->api_key,
                $p->model ?: 'claude-sonnet-5',
                (int) ($o['max_tokens'] ?? 4096),
                (int) ($o['timeout'] ?? 120),
            ),
            'gemini' => new GeminiTextDriver(
                $p->base_url ?: 'https://generativelanguage.googleapis.com/v1beta',
                (string) $p->api_key,
                $p->model ?: 'gemini-2.5-flash',
                (float) ($o['temperature'] ?? 0.2),
                (int) ($o['timeout'] ?? 120),
            ),
            default => throw new \RuntimeException("Text driver [{$p->driver}] is not available yet."),
        };
    }
}

<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\ImageDriver;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI image generation (gpt-image-1 / DALL·E 3) via /v1/images/generations.
 * Returns a base64 data URI. The API key is passed in from the configured
 * AiProvider (stored encrypted) — never hard-coded or logged.
 */
class OpenAiImageDriver implements ImageDriver
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model = 'gpt-image-1',
        private string $size = '1536x1024',
        private int $timeout = 180,
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('No API key configured for the image provider.');
        }

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'size' => $options['size'] ?? $this->size,
            'n' => 1,
        ];

        // DALL·E models must be asked for base64; gpt-image-1 always returns it.
        if (str_starts_with($this->model, 'dall-e')) {
            $payload['response_format'] = 'b64_json';
            if (! empty($options['quality'])) {
                $payload['quality'] = $options['quality'];
            }
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/images/generations', $payload);

        if (! $response->successful()) {
            $msg = data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            throw new \RuntimeException('Image generation failed: '.$msg);
        }

        $b64 = data_get($response->json(), 'data.0.b64_json');
        if (! $b64) {
            throw new \RuntimeException('Image provider returned no image data.');
        }

        return 'data:image/png;base64,'.$b64;
    }
}

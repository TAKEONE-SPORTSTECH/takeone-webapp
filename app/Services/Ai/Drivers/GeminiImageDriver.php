<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\ImageDriver;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini image generation via generateContent on an image-capable model
 * (e.g. gemini-2.5-flash-image-preview / gemini-2.0-flash-preview-image-generation).
 * Returns the first inline image part as a `data:image/...;base64,...` URI.
 * Key is sent via the x-goog-api-key header.
 */
class GeminiImageDriver implements ImageDriver
{
    public function __construct(
        private string $baseUrl,   // https://generativelanguage.googleapis.com/v1beta
        private string $apiKey,
        private string $model = 'gemini-2.5-flash-image',
        private int $timeout = 180,
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('No API key configured for the image provider.');
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $url = rtrim($this->baseUrl, '/').'/models/'.$this->model.':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            throw new \RuntimeException('Image generation failed: '.$msg);
        }

        foreach (data_get($response->json(), 'candidates.0.content.parts', []) as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if ($data) {
                $mime = $part['inlineData']['mimeType'] ?? $part['inline_data']['mime_type'] ?? 'image/png';

                return 'data:'.$mime.';base64,'.$data;
            }
        }

        throw new \RuntimeException('Gemini returned no image data (the model may not support image output).');
    }
}

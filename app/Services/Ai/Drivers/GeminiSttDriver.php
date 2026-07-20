<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\SttDriver;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini speech-to-text. Gemini understands audio natively, so we send
 * the recording as inline audio to a standard model and ask for a verbatim
 * transcript.
 */
class GeminiSttDriver implements SttDriver
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model = 'gemini-2.5-flash',
        private int $timeout = 120,
    ) {}

    public function transcribe(string $audioBase64, string $mime, array $options = []): string
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('No API key configured for the STT provider.');
        }

        $instruction = 'Transcribe this audio to text verbatim. Return ONLY the transcript, with no commentary, labels or quotation marks. If the audio is in Arabic, transcribe in Arabic; if in English, in English.';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['inlineData' => ['mimeType' => $mime, 'data' => $audioBase64]],
                    ['text' => $instruction],
                ],
            ]],
            'generationConfig' => ['temperature' => 0],
        ];

        $url = rtrim($this->baseUrl, '/').'/models/'.$this->model.':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            throw new \RuntimeException('Transcription failed: '.$msg);
        }

        $text = '';
        foreach (data_get($response->json(), 'candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }
}

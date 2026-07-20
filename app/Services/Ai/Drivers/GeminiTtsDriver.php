<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\TtsDriver;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini text-to-speech (gemini-2.5-flash-preview-tts). The model
 * returns raw PCM (signed 16-bit, mono, usually 24 kHz), which browsers can't
 * play directly — so we wrap it in a minimal WAV container and return
 * audio/wav bytes ready for an <audio> element.
 */
class GeminiTtsDriver implements TtsDriver
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model = 'gemini-2.5-flash-preview-tts',
        private string $voice = 'Kore',
        private int $timeout = 120,
    ) {}

    public function synthesize(string $text, array $options = []): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('No API key configured for the TTS provider.');
        }

        $payload = [
            'contents' => [['parts' => [['text' => $text]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => ['voiceName' => $options['voice'] ?? $this->voice],
                    ],
                ],
            ],
        ];

        $url = rtrim($this->baseUrl, '/').'/models/'.$this->model.':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            throw new \RuntimeException('Speech synthesis failed: '.$msg);
        }

        $part = data_get($response->json(), 'candidates.0.content.parts.0.inlineData');
        $b64 = $part['data'] ?? null;
        if (! $b64) {
            throw new \RuntimeException('The TTS model returned no audio.');
        }

        $pcm = base64_decode($b64);
        $rate = $this->sampleRateFromMime($part['mimeType'] ?? '');

        return ['mime' => 'audio/wav', 'data' => $this->pcmToWav($pcm, $rate)];
    }

    /** Parse "audio/L16;codec=pcm;rate=24000" → 24000 (default 24000). */
    private function sampleRateFromMime(string $mime): int
    {
        if (preg_match('/rate=(\d+)/', $mime, $m)) {
            return (int) $m[1];
        }

        return 24000;
    }

    /** Wrap raw 16-bit mono PCM in a WAV container. */
    private function pcmToWav(string $pcm, int $sampleRate, int $channels = 1, int $bits = 16): string
    {
        $byteRate = $sampleRate * $channels * ($bits / 8);
        $blockAlign = $channels * ($bits / 8);
        $dataLen = strlen($pcm);

        $header = 'RIFF'
            .pack('V', 36 + $dataLen)
            .'WAVE'
            .'fmt '
            .pack('V', 16)                 // PCM fmt chunk size
            .pack('v', 1)                  // audio format = PCM
            .pack('v', $channels)
            .pack('V', $sampleRate)
            .pack('V', $byteRate)
            .pack('v', $blockAlign)
            .pack('v', $bits)
            .'data'
            .pack('V', $dataLen);

        return $header.$pcm;
    }
}

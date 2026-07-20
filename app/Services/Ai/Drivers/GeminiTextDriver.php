<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\TextDriver;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini text driver (generativelanguage.googleapis.com). Speaks the
 * internal Ollama-native message shape and translates to/from Gemini's
 * `generateContent` format: system → systemInstruction, user/assistant →
 * contents (role user/model), tools → functionDeclarations, tool calls →
 * functionCall / functionResponse parts.
 *
 * The API key is passed via the x-goog-api-key header (not the URL) so it
 * never lands in request-log URLs.
 */
class GeminiTextDriver implements TextDriver
{
    public function __construct(
        private string $baseUrl,   // e.g. https://generativelanguage.googleapis.com/v1beta
        private string $apiKey,
        private string $model = 'gemini-2.5-flash',
        private float $temperature = 0.2,
        private int $timeout = 120,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('No API key configured for the Gemini provider.');
        }

        [$system, $contents] = $this->toGemini($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? $this->temperature),
            ],
        ];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if (! empty($tools)) {
            $payload['tools'] = [['functionDeclarations' => $this->toGeminiTools($tools)]];
        }

        $url = rtrim($this->baseUrl, '/').'/models/'.$this->model.':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $msg = data_get($response->json(), 'error.message', 'HTTP '.$response->status());
            throw new \RuntimeException('Gemini request failed: '.$msg);
        }

        $text = '';
        $calls = [];
        foreach (data_get($response->json(), 'candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $calls[] = ['function' => [
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => (array) ($part['functionCall']['args'] ?? []),
                ]];
            }
        }

        return ['role' => 'assistant', 'content' => $text, 'tool_calls' => $calls];
    }

    /**
     * Internal → Gemini. Returns [systemString, contents].
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @return array{0:string,1:array<int,array<string,mixed>>}
     */
    private function toGemini(array $messages): array
    {
        $system = [];
        $contents = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                $system[] = (string) ($m['content'] ?? '');
            } elseif ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $parts = [];
                if (($m['content'] ?? '') !== '') {
                    $parts[] = ['text' => (string) $m['content']];
                }
                foreach ($m['tool_calls'] as $tc) {
                    $parts[] = ['functionCall' => [
                        'name' => data_get($tc, 'function.name', ''),
                        'args' => (object) data_get($tc, 'function.arguments', []),
                    ]];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];
            } elseif ($role === 'tool') {
                $contents[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name' => (string) ($m['tool_name'] ?? 'tool'),
                        'response' => ['result' => (string) ($m['content'] ?? '')],
                    ],
                ]]];
            } else {
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) ($m['content'] ?? '')]],
                ];
            }
        }

        return [trim(implode("\n\n", $system)), $contents];
    }

    /**
     * OpenAI-style tool schemas → Gemini functionDeclarations.
     *
     * @param  array<int,array<string,mixed>>  $tools
     * @return array<int,array<string,mixed>>
     */
    private function toGeminiTools(array $tools): array
    {
        return array_map(function ($t) {
            $fn = $t['function'] ?? $t;

            return array_filter([
                'name' => $fn['name'] ?? '',
                'description' => $fn['description'] ?? '',
                'parameters' => $fn['parameters'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }, $tools);
    }
}

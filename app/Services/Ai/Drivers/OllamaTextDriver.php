<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\TextDriver;
use Illuminate\Support\Facades\Http;

/**
 * Native Ollama /api/chat driver (also the built-in default). The internal
 * message/tool shape IS Ollama-native, so this is a near pass-through — it just
 * normalises tool_call arguments to arrays.
 */
class OllamaTextDriver implements TextDriver
{
    public function __construct(
        private string $baseUrl,
        private string $model,
        private float $temperature = 0.2,
        private int $timeout = 120,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature' => $this->temperature],
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/api/chat', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Ollama request failed: HTTP '.$response->status());
        }

        $message = (array) $response->json('message', []);

        // Normalise tool_call arguments to arrays (Ollama already returns objects).
        $calls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $args = $call['function']['arguments'] ?? [];
            if (is_string($args)) {
                $args = json_decode($args, true) ?: [];
            }
            $calls[] = ['function' => ['name' => $call['function']['name'] ?? '', 'arguments' => $args]];
        }

        return [
            'role' => 'assistant',
            'content' => (string) ($message['content'] ?? ''),
            'tool_calls' => $calls,
        ];
    }
}

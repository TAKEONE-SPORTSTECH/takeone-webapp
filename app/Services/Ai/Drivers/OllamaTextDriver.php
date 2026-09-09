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

        /*
         * Constrained decoding, when the caller says it needs JSON.
         *
         * Opt-in and absent by default, so nothing that already calls this
         * driver changes behaviour. It matters most for the models people
         * actually self-host: a 7B or 30B model asked politely for JSON will
         * often wrap it in prose or a code fence, and while the caller can
         * salvage that, `format: json` makes Ollama enforce the grammar during
         * generation so there is nothing to salvage. This is the difference
         * between a local model being usable for structured work and not.
         */
        if (! empty($options['json'])) {
            $payload['format'] = 'json';
        }

        /*
         * Keep the weights resident between calls.
         *
         * Ollama unloads a model five minutes after its last request, so on a
         * quiet day EVERY call pays a full cold load first — for a 19 GB model
         * that is ten to fifteen seconds before a single token is generated.
         * The server was measured with nothing loaded at all.
         *
         * Opt-in, because it holds VRAM on a machine we may not own.
         */
        if (! empty($options['keep_alive'])) {
            $payload['keep_alive'] = (string) $options['keep_alive'];
        }

        // Let the caller raise the ceiling; Ollama caps generation at 128
        // tokens by default, which silently truncates anything substantial.
        if (! empty($options['max_tokens'])) {
            $payload['options']['num_predict'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['options']['temperature'] = (float) $options['temperature'];
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

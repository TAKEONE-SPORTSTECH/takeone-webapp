<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\TextDriver;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Chat Completions driver — also works with any OpenAI-compatible
 * endpoint (LM Studio, vLLM, llama.cpp, Ollama's /v1, …) by setting base_url.
 * Translates the internal Ollama-native message/tool shape to/from OpenAI's.
 */
class OpenAiTextDriver implements TextDriver
{
    public function __construct(
        private string $baseUrl,   // e.g. https://api.openai.com/v1
        private string $apiKey,
        private string $model,
        private float $temperature = 0.2,
        private int $timeout = 120,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->toOpenAi($messages),
            'temperature' => $this->temperature,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;            // already OpenAI function schema
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI request failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        $msg = (array) $response->json('choices.0.message', []);

        $calls = [];
        foreach ($msg['tool_calls'] ?? [] as $call) {
            $args = $call['function']['arguments'] ?? '{}';
            if (is_string($args)) {
                $args = json_decode($args, true) ?: [];
            }
            $calls[] = ['function' => ['name' => $call['function']['name'] ?? '', 'arguments' => $args]];
        }

        return [
            'role' => 'assistant',
            'content' => (string) ($msg['content'] ?? ''),
            'tool_calls' => $calls,
        ];
    }

    /**
     * Internal → OpenAI. Assistant tool_calls need ids; the following tool
     * results are matched to them in order (FIFO), which holds because each
     * assistant turn is immediately followed by its tool results.
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @return array<int,array<string,mixed>>
     */
    private function toOpenAi(array $messages): array
    {
        $out = [];
        $idQueue = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $tcs = [];
                foreach ($m['tool_calls'] as $i => $c) {
                    $id = 'call_'.count($out).'_'.$i;
                    $idQueue[] = $id;
                    $tcs[] = [
                        'id' => $id,
                        'type' => 'function',
                        'function' => [
                            'name' => $c['function']['name'] ?? '',
                            'arguments' => json_encode($c['function']['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
                $out[] = ['role' => 'assistant', 'content' => (string) ($m['content'] ?? ''), 'tool_calls' => $tcs];
            } elseif ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => array_shift($idQueue) ?: ('call_'.count($out)),
                    'content' => (string) ($m['content'] ?? ''),
                ];
            } else {
                $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
            }
        }

        return $out;
    }
}

<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\TextDriver;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API driver. Anthropic differs from OpenAI/Ollama: the
 * system prompt is a top-level field, tools use {name, description, input_schema},
 * tool calls are `tool_use` content blocks, and tool results go back in a USER
 * message as `tool_result` blocks. This translates the internal shape to that.
 */
class AnthropicTextDriver implements TextDriver
{
    public function __construct(
        private string $baseUrl,   // e.g. https://api.anthropic.com
        private string $apiKey,
        private string $model,
        private int $maxTokens = 4096,
        private int $timeout = 120,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        [$system, $converted] = $this->toAnthropic($messages);

        $payload = [
            'model' => $this->model,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->maxTokens),
            'messages' => $converted,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }
        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($t) => [
                'name' => $t['function']['name'] ?? '',
                'description' => $t['function']['description'] ?? '',
                'input_schema' => $t['function']['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], $tools);
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/v1/messages', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Anthropic request failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        $text = '';
        $calls = [];
        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = ['function' => ['name' => $block['name'] ?? '', 'arguments' => (array) ($block['input'] ?? [])]];
            }
        }

        return ['role' => 'assistant', 'content' => $text, 'tool_calls' => $calls];
    }

    /**
     * Internal → Anthropic. Returns [systemString, messages]. System messages
     * are hoisted out; assistant tool_calls become tool_use blocks; consecutive
     * tool results are merged into one user message of tool_result blocks.
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @return array{0:string,1:array<int,array<string,mixed>>}
     */
    private function toAnthropic(array $messages): array
    {
        $system = [];
        $out = [];
        $idQueue = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                $system[] = (string) ($m['content'] ?? '');
            } elseif ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $blocks = [];
                if (($m['content'] ?? '') !== '') {
                    $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];
                }
                foreach ($m['tool_calls'] as $i => $c) {
                    $id = 'toolu_'.count($out).'_'.$i;
                    $idQueue[] = $id;
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $id,
                        'name' => $c['function']['name'] ?? '',
                        'input' => (object) ($c['function']['arguments'] ?? []),
                    ];
                }
                $out[] = ['role' => 'assistant', 'content' => $blocks];
            } elseif ($role === 'tool') {
                $block = [
                    'type' => 'tool_result',
                    'tool_use_id' => array_shift($idQueue) ?: ('toolu_'.count($out)),
                    'content' => (string) ($m['content'] ?? ''),
                ];
                // Merge into the previous user message if it already holds tool_results.
                $last = count($out) - 1;
                if ($last >= 0 && $out[$last]['role'] === 'user' && is_array($out[$last]['content'])) {
                    $out[$last]['content'][] = $block;
                } else {
                    $out[] = ['role' => 'user', 'content' => [$block]];
                }
            } else {
                $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
            }
        }

        return [trim(implode("\n\n", $system)), $out];
    }
}

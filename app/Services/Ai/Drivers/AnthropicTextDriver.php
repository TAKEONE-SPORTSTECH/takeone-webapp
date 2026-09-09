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
        /*
         * Optional, and only needed for an ORGANISATION-level key.
         *
         * A key created inside a workspace in the Anthropic console already
         * knows which workspace it belongs to. A key created at the
         * organisation level does not, and every request from it is rejected
         * with HTTP 400 "This API key is not scoped to a workspace, so this
         * request must include the anthropic-workspace-id header" — which reads
         * like an authentication failure and is not one. Set this and the same
         * key works; leave it null and nothing changes for the keys that
         * already worked.
         */
        private ?string $workspaceId = null,
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

        /*
         * How hard to think about it.
         *
         * ⚠️ On current Claude models thinking is ON by default and effort
         * defaults to `high`, so a request that says nothing is asking for the
         * most expensive reasoning the model can do. That is right for a coach
         * reasoning about a club, and absurd for rewriting seventeen short
         * strings into Portuguese: measured, the prompt is ~1,200 tokens and
         * the answer ~800, yet 2,000-3,000 tokens were being generated — the
         * rest was deliberation nobody asked for, at ~25 seconds a language.
         *
         * `effort` is GA and needs no beta header. Absent, behaviour is exactly
         * what it was, so no existing caller changes.
         *
         * Deliberately NOT `thinking: {type: "disabled"}`: on Opus 5 that is
         * accepted only at effort ≤ high and carries two documented failure
         * modes — the model can write a tool call, or a <thinking> tag, into
         * the VISIBLE text. For a caller that parses the reply as JSON that is
         * silent corruption. Low effort with thinking on is nearly as fast and
         * cannot do that.
         */
        if (! empty($options['effort'])) {
            $payload['output_config'] = ['effort' => (string) $options['effort']];
        }
        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($t) => [
                'name' => $t['function']['name'] ?? '',
                'description' => $t['function']['description'] ?? '',
                'input_schema' => $t['function']['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], $tools);
        }

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ];

        if (filled($this->workspaceId)) {
            $headers['anthropic-workspace-id'] = $this->workspaceId;
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($headers)
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

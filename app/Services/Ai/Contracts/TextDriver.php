<?php

namespace App\Services\Ai\Contracts;

/**
 * A text/chat provider that supports tool-calling.
 *
 * Internal message + tool-call shape is Ollama-native (the format the agent loop
 * already speaks); non-Ollama drivers translate to/from their own wire format:
 *
 *   messages: [
 *     ['role' => 'system'|'user'|'assistant'|'tool', 'content' => string,
 *      'tool_calls' => [['function' => ['name' => string, 'arguments' => array]]]?,  // assistant turns
 *      'tool_name' => string?, 'tool_call_id' => string?]                            // tool results
 *   ]
 *   tools: OpenAI-style function schemas ([['type'=>'function','function'=>[...]]])
 *
 * chat() returns the assistant message in that same internal shape:
 *   ['role' => 'assistant', 'content' => string, 'tool_calls' => [...]]
 */
interface TextDriver
{
    /**
     * @param  array<int,array<string,mixed>>  $messages
     * @param  array<int,array<string,mixed>>  $tools
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;
}

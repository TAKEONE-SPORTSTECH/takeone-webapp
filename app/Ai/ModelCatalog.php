<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Asks a text provider which models it will actually serve, so the admin picks
 * one from a list instead of typing an id from memory.
 *
 * Each vendor has its own listing endpoint and shape; this normalises them all
 * to [['id' => …, 'label' => …], …]. The key is sent the same way the matching
 * text driver sends it, and never appears in a returned message.
 */
class ModelCatalog
{
    public const DRIVERS = ['ollama', 'openai', 'anthropic', 'gemini'];

    private const LIMIT = 500;

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function list(string $driver, ?string $baseUrl, ?string $apiKey, ?string $workspaceId = null, int $timeout = 20): array
    {
        $models = match ($driver) {
            'ollama' => $this->ollama($baseUrl ?: (string) config('copilot.base_url'), $timeout),
            'openai' => $this->openai($baseUrl ?: 'https://api.openai.com/v1', (string) $apiKey, $timeout),
            'anthropic' => $this->anthropic($baseUrl ?: 'https://api.anthropic.com', (string) $apiKey, $workspaceId, $timeout),
            'gemini' => $this->gemini($baseUrl ?: 'https://generativelanguage.googleapis.com/v1beta', (string) $apiKey, $timeout),
            default => throw new \RuntimeException('Listing models is available for text providers only.'),
        };

        $models = collect($models)
            ->filter(fn ($m) => is_string($m['id'] ?? null) && $m['id'] !== '' && mb_strlen($m['id']) <= 120)
            ->unique('id')
            ->sortBy('id', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(self::LIMIT)
            ->map(fn ($m) => ['id' => $m['id'], 'label' => mb_substr((string) ($m['label'] ?? $m['id']), 0, 120)])
            ->values()
            ->all();

        return $models;
    }

    private function ollama(string $base, int $timeout): array
    {
        $res = Http::timeout($timeout)->acceptJson()->get(rtrim($base, '/').'/api/tags');
        $this->fail($res, 'Ollama');

        return array_map(fn ($m) => ['id' => $m['name'] ?? $m['model'] ?? null, 'label' => $m['name'] ?? null], (array) $res->json('models', []));
    }

    private function openai(string $base, string $key, int $timeout): array
    {
        $req = Http::timeout($timeout)->acceptJson();
        if ($key !== '') {
            $req = $req->withToken($key);
        }
        $res = $req->get(rtrim($base, '/').'/models');
        $this->fail($res, 'OpenAI');

        return array_map(fn ($m) => ['id' => $m['id'] ?? null, 'label' => $m['id'] ?? null], (array) $res->json('data', []));
    }

    private function anthropic(string $base, string $key, ?string $workspaceId, int $timeout): array
    {
        $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
        if (filled($workspaceId)) {
            $headers['anthropic-workspace-id'] = $workspaceId;
        }

        $res = Http::timeout($timeout)->withHeaders($headers)->acceptJson()
            ->get(rtrim($base, '/').'/v1/models', ['limit' => 1000]);
        $this->fail($res, 'Anthropic');

        return array_map(fn ($m) => ['id' => $m['id'] ?? null, 'label' => $m['display_name'] ?? $m['id'] ?? null], (array) $res->json('data', []));
    }

    private function gemini(string $base, string $key, int $timeout): array
    {
        $res = Http::timeout($timeout)->withHeaders(['x-goog-api-key' => $key])->acceptJson()
            ->get(rtrim($base, '/').'/models', ['pageSize' => 1000]);
        $this->fail($res, 'Gemini');

        // Only models that can chat; the driver calls :generateContent.
        return collect((array) $res->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true))
            ->map(fn ($m) => [
                'id' => preg_replace('#^models/#', '', (string) ($m['name'] ?? '')),
                'label' => $m['displayName'] ?? null,
            ])->all();
    }

    private function fail($res, string $vendor): void
    {
        if ($res->failed()) {
            throw new \RuntimeException("{$vendor} did not list its models: HTTP ".$res->status());
        }
    }
}

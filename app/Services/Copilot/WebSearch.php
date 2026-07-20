<?php

namespace App\Services\Copilot;

use Illuminate\Support\Facades\Http;

/**
 * Web search via a self-hosted SearXNG instance (JSON API). Runs server-side —
 * the model only receives a short list of {title, url, snippet}. SearXNG lives
 * on a trusted internal address, so this call intentionally does NOT go through
 * the SSRF-guarded fetcher.
 */
class WebSearch
{
    /**
     * @return array<string,mixed>  {ok, query, results:[{title,url,snippet}]} or {ok:false,error}
     */
    public function search(string $query, ?int $limit = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'error' => 'Empty query.'];
        }

        $limit = $limit ?: (int) config('copilot.web.max_results', 6);
        $base = (string) config('copilot.web.searxng_url');

        try {
            $response = Http::timeout((int) config('copilot.web.search_timeout', 15))
                ->acceptJson()
                ->get($base.'/search', [
                    'q' => $query,
                    'format' => 'json',
                    'safesearch' => 1,
                    'language' => 'en',
                ]);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'Search is unavailable right now.'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'error' => 'Search backend returned HTTP '.$response->status().'.'];
        }

        $results = collect($response->json('results', []))
            ->filter(fn ($r) => ! empty($r['url']) && ! empty($r['title']))
            ->take($limit)
            ->map(fn ($r) => [
                'title' => (string) $r['title'],
                'url' => (string) $r['url'],
                'snippet' => mb_substr(trim((string) ($r['content'] ?? '')), 0, 300),
            ])
            ->values()
            ->all();

        if ($results === []) {
            return ['ok' => true, 'query' => $query, 'results' => [], 'message' => 'No results found.'];
        }

        return ['ok' => true, 'query' => $query, 'results' => $results];
    }
}

<?php

namespace App\Services;

use App\Models\ActivityCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds REAL, embeddable YouTube videos for an activity — no YouTube Data API
 * key required. It mirrors the manual research flow: run a search query, take
 * the candidate video ids off YouTube's public results page, then confirm each
 * one via YouTube's oEmbed endpoint (which only answers for videos that exist
 * AND allow embedding, and hands back the real title + channel). The AI writes
 * the queries; this service turns them into verified videos so a generated
 * activity never ships a dead or hallucinated clip.
 *
 * Everything is best-effort and defensive: network/parse failures degrade to
 * "no video for this slot" rather than throwing.
 */
class ActivityVideoResearcher
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** Titles hinting at controversy/negativity — skipped for a cleaner set when a better candidate exists. */
    private const NEGATIVE = ['useless', 'fake', 'does not work', "doesn't work", 'debunk', 'exposed', 'street fight', 'bullshit', 'vs mma', 'gets destroyed', 'reaction'];

    /**
     * Resolve an ordered AI plan of {role, query} slots into real videos.
     *
     * @param  array<int,array<string,mixed>>  $plan
     * @return array<int,array{id:string,title:string,source:?string,role:?string}>
     */
    public function resolvePlan(array $plan, int $max = 8): array
    {
        $out = [];
        $seen = [];

        foreach ($plan as $slot) {
            if (count($out) >= $max) {
                break;
            }
            if (! is_array($slot)) {
                continue;
            }

            $query = trim((string) ($slot['query'] ?? ''));
            if ($query === '') {
                continue;
            }

            $found = $this->firstEmbeddable($this->search($query), $seen);
            if ($found !== null) {
                $seen[$found['id']] = true;
                $found['role'] = trim((string) ($slot['role'] ?? '')) ?: null;
                $out[] = $found;
            }
        }

        return $out;
    }

    /**
     * Verify a single pasted URL or id (for manual admin add). Returns the
     * normalized video, or null if it isn't a real embeddable YouTube video.
     *
     * @return array{id:string,title:string,source:?string}|null
     */
    public function verifyOne(string $urlOrId): ?array
    {
        $id = $this->extractId($urlOrId);

        return $id === null ? null : $this->verify($id);
    }

    /**
     * Candidate video ids from YouTube's public results page for a query.
     *
     * @return array<int,string>
     */
    public function search(string $query): array
    {
        try {
            $resp = Http::withHeaders([
                'User-Agent' => self::UA,
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(12)->get('https://www.youtube.com/results', ['search_query' => $query]);

            if (! $resp->ok()) {
                return [];
            }

            preg_match_all('/"videoId":"([A-Za-z0-9_-]{11})"/', $resp->body(), $m);

            return array_values(array_unique($m[1] ?? []));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * First embeddable candidate, preferring a non-controversial title. Checks
     * at most a handful of ids per query to bound outbound requests.
     *
     * @param  array<int,string>  $ids
     * @param  array<string,bool>  $seen  ids already used (deduped across slots)
     * @return array{id:string,title:string,source:?string}|null
     */
    private function firstEmbeddable(array $ids, array $seen): ?array
    {
        $verified = [];
        $checked = 0;

        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            if ($checked >= 6) {
                break;
            }
            $checked++;

            $v = $this->verify($id);
            if ($v === null) {
                continue;
            }

            if (! $this->looksNegative($v['title'])) {
                return $v; // clean candidate — take it
            }
            $verified[] = $v; // keep as fallback
        }

        return $verified[0] ?? null;
    }

    /**
     * oEmbed a single id → normalized video, or null when it doesn't exist / is
     * private / disallows embedding (all of which return a non-200).
     *
     * @return array{id:string,title:string,source:?string}|null
     */
    public function verify(string $id): ?array
    {
        if (! preg_match(ActivityCatalog::YOUTUBE_ID, $id)) {
            return null;
        }

        try {
            $resp = Http::timeout(8)->get('https://www.youtube.com/oembed', [
                'url' => 'https://www.youtube.com/watch?v='.$id,
                'format' => 'json',
            ]);

            if (! $resp->ok()) {
                return null;
            }

            $data = $resp->json();

            return [
                'id' => $id,
                'title' => Str::limit(trim((string) ($data['title'] ?? '')), 140, '') ?: 'Video',
                'source' => Str::limit(trim((string) ($data['author_name'] ?? '')), 80, '') ?: null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function looksNegative(string $title): bool
    {
        $t = mb_strtolower($title);
        foreach (self::NEGATIVE as $bad) {
            if (str_contains($t, $bad)) {
                return true;
            }
        }

        return false;
    }

    /** Pull a YouTube id out of a bare id or any common YouTube URL shape. */
    private function extractId(string $s): ?string
    {
        $s = trim($s);
        if (preg_match(ActivityCatalog::YOUTUBE_ID, $s)) {
            return $s;
        }
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $s, $m)) {
            return $m[1];
        }

        return null;
    }
}

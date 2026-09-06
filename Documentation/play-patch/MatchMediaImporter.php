<?php

namespace App\Services;

use App\Models\SportsMatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch a pushed bout's faces and crests onto this platform.
 *
 * takeone sends URLs — a competitor's photo, a club's logo — because those are
 * ITS records and it may revoke or replace them at any time. This platform
 * renders images from its OWN storage (`media.participant1_photo`,
 * `media.club1_logo`), the way the manual annotation flow always has, so the
 * push has to become files here.
 *
 * That is deliberate rather than lazy: hot-linking would mean a bout page that
 * breaks when the other platform is down, leaks a viewer's IP to it on every
 * play, and shows a competitor's photograph after they have removed it. A copy
 * taken once, at push time, is the same trade the human-driven
 * `TakeoneMatchFiller` already makes — and this deliberately mirrors its rules
 * rather than inventing softer ones:
 *
 *   · the URL must be on takeone's own origin, re-checked here rather than
 *     trusted, because this is the one place a remote string becomes a
 *     server-side request;
 *   · the file type is decided by SNIFFING THE BYTES, never by the declared
 *     content type or the extension in the URL;
 *   · anything not in the image whitelist, or over the size cap, is dropped;
 *   · a failure is never fatal — the bout is worth having without a face on it.
 */
class MatchMediaImporter
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Which pushed URL becomes which media key.
     *
     * p1 is participant1 is takeone's side `a`; p2 is `b`. The mapping is the
     * one `headerData()` already reads, so an imported image lands exactly where
     * the scoreboard and VS partials look for it.
     */
    private const SLOTS = [
        ['a', 'photo', 'participant1_photo'],
        ['b', 'photo', 'participant2_photo'],
        ['a', 'logo', 'club1_logo'],
        ['b', 'logo', 'club2_logo'],
    ];

    public function __construct(private \App\Services\NasSyncService $nas) {}

    /**
     * Import whatever the push carried, and return the new `media` group.
     *
     * Only fetches what CHANGED: the source URL is remembered beside the file,
     * so a bout pushed twenty times during a competition downloads its
     * photographs once. A URL that has gone (a competitor made their picture
     * private) clears the stored image rather than leaving a stale face.
     *
     * @param  array<string, mixed>  $participants  the pushed group
     * @return array<string, mixed>  the media group to save
     */
    public function apply(SportsMatch $match, array $participants): array
    {
        $media = (array) ($match->media ?? []);

        foreach (self::SLOTS as [$side, $kind, $key]) {
            $url = $kind === 'photo'
                ? ($participants[$side]['photo'] ?? null)
                : ($participants[$side]['club']['logo'] ?? null);

            $url = is_string($url) && $url !== '' ? $url : null;
            $known = $media[$key.'_src'] ?? null;

            if ($url === null) {
                // Withdrawn upstream — a competitor who made their photo private
                // must stop appearing here too. The file is left on disk (this is
                // not a deletion path) but nothing points at it any more.
                if ($known !== null) {
                    unset($media[$key], $media[$key.'_src']);
                }

                continue;
            }

            if ($known === $url && ! empty($media[$key])) {
                continue;   // same picture as last time
            }

            $path = $this->fetch($url, $match, $key);

            if ($path !== null) {
                $media[$key] = $path;
                $media[$key.'_src'] = $url;
            }
        }

        return $media;
    }

    /** Download one image, verify it by its bytes, and store it. */
    private function fetch(string $url, SportsMatch $match, string $key): ?string
    {
        if (! $match->id) {
            return null;
        }

        $base = parse_url(rtrim((string) config('services.takeone.url'), '/'));
        $got = parse_url($url);

        if (! $got || ($got['scheme'] ?? '') !== ($base['scheme'] ?? '') || ($got['host'] ?? '') !== ($base['host'] ?? '')) {
            Log::warning('TAKEONE push image rejected — foreign origin', ['host' => $got['host'] ?? null]);

            return null;
        }

        try {
            $res = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($url);

            if (! $res->successful()) {
                return null;
            }

            $bytes = $res->body();

            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                return null;
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            $ext = self::ALLOWED[$mime] ?? null;

            if (! $ext) {
                Log::warning('TAKEONE push image rejected — not a whitelisted image', ['mime' => $mime]);

                return null;
            }

            $slug = $this->nas->userSlug($match->user);
            $rel = "users/{$slug}/sports/{$match->id}/{$key}.{$ext}";
            $abs = storage_path('app/'.$rel);

            @mkdir(dirname($abs), 0755, true);

            if (file_put_contents($abs, $bytes) === false) {
                return null;
            }

            if ($this->nas->isEnabled()) {
                $this->nas->mkdirp(dirname($rel));

                if ($this->nas->putFile($abs, $rel)) {
                    @unlink($abs);
                }
            }

            return $rel;
        } catch (\Throwable $e) {
            Log::warning('TAKEONE push image import failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

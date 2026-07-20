<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCatalog;
use App\Services\Ai\AiManager;
use App\Support\HtmlSanitizer;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Super-admin management of the GLOBAL activity directory (the shared
 * `activity_catalog` clubs reuse). Access is gated by the route group's
 * `role:super-admin` middleware; a defensive check is kept here too.
 */
class PlatformActivityController extends Controller
{
    use StoresBase64Images;

    private function guard(): void
    {
        abort_unless(optional(auth()->user())->hasRole('super-admin'), 403);
    }

    /**
     * AI agent: generate the full research write-up (EN + AR) and an image
     * prompt for an activity from just its name (+ optional style/variant),
     * following the deep, structured, bilingual format used across the
     * directory. Uses the configured text provider (falls back to Ollama).
     * Does NOT save — the admin reviews and edits, then saves normally.
     */
    public function generateContent(Request $request, AiManager $ai)
    {
        $this->guard();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:120'],
        ]);

        $subject = trim($data['name']).(filled($data['style'] ?? null) ? ' — '.trim($data['style']) : '');

        $messages = [
            ['role' => 'system', 'content' => $this->contentSystemPrompt()],
            ['role' => 'user', 'content' => "Sport / martial art / activity: \"{$subject}\".\nReturn ONLY the JSON object described, fully filled for this activity, in both English and Arabic."],
        ];

        try {
            $reply = $ai->text()->chat($messages, [], ['temperature' => 0.4]);
        } catch (\Throwable $e) {
            // If a HOSTED provider (e.g. Gemini) is rate-limited/quota-exhausted or
            // temporarily unavailable, transparently retry once on the built-in
            // local model so generation keeps working. Non-retryable errors (bad
            // key, etc.) surface as-is.
            if ($ai->hasHostedTextProvider() && $this->isRetryableProviderError($e->getMessage())) {
                try {
                    $reply = $ai->textFallback()->chat($messages, [], ['temperature' => 0.4]);
                } catch (\Throwable $e2) {
                    return response()->json(['success' => false, 'message' => $this->friendlyTextError($e2->getMessage())], 422);
                }
            } else {
                return response()->json(['success' => false, 'message' => $this->friendlyTextError($e->getMessage())], 422);
            }
        }

        $parsed = $this->extractJson((string) ($reply['content'] ?? ''));
        if (! is_array($parsed) || blank($parsed['description_en'] ?? null)) {
            return response()->json(['success' => false, 'message' => 'The AI returned an unexpected response. Please try again.'], 422);
        }

        // Sanitize, then verify every resource link: it must resolve, return real
        // content, AND that content must actually be about this activity. Dead,
        // empty, soft-404 or off-topic links are dropped so we never register a
        // useless reference. Unique URLs are checked once and reused across the
        // EN/AR copies (they share the same sources).
        $linkCache = [];
        $descEn = $this->pruneDeadLinks(HtmlSanitizer::clean($parsed['description_en'] ?? '') ?? '', $data['name'], $linkCache);
        $descAr = $this->pruneDeadLinks(HtmlSanitizer::clean($parsed['description_ar'] ?? '') ?? '', $data['name'], $linkCache);

        return response()->json([
            'success' => true,
            'message' => 'Draft generated — review, tweak and save.',
            'name_ar' => is_string($parsed['name_ar'] ?? null) ? trim($parsed['name_ar']) : '',
            'description' => $descEn,
            'description_ar' => $descAr,
            'image_prompt' => is_string($parsed['image_prompt'] ?? null) ? trim($parsed['image_prompt']) : '',
        ]);
    }

    /**
     * Remove resource links that don't resolve, return no real content, or whose
     * page isn't actually about $subject — so a generated write-up never ships a
     * dead or irrelevant reference. The enclosing <li> is dropped when present
     * (else just the <a>). Results are cached per request via $cache so the same
     * URL isn't fetched twice across EN/AR copies.
     */
    private function pruneDeadLinks(?string $html, string $subject, array &$cache): ?string
    {
        if (blank($html)) {
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.'<div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//*[@id="__root"]')->item(0);
        if (! $root instanceof \DOMElement) {
            return $html;
        }

        foreach (iterator_to_array($xpath->query('//a[@href]')) as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || ! preg_match('#^https?://#i', $href)) {
                continue;
            }
            if ($this->linkIsUseful($href, $subject, $cache)) {
                continue;
            }

            // Prefer removing the whole list item so no empty bullet is left.
            $node = $a;
            while ($node instanceof \DOMNode && $node !== $root && strtolower($node->nodeName) !== 'li') {
                $node = $node->parentNode;
            }
            $target = ($node instanceof \DOMElement && strtolower($node->nodeName) === 'li') ? $node : $a;
            $target->parentNode?->removeChild($target);
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    /**
     * Fetch the URL and decide whether to keep it. We only DROP a link when we
     * are confident it's useless:
     *   - a definitive 404/410, or a dead host (DNS failure / connection refused);
     *   - OR a page we successfully read (2xx) that is empty or not about $subject
     *     — this catches soft-404s and redirects-to-homepage that return 200 with
     *     the wrong/no content, which a status check alone misses.
     * Everything uncertain — bot-blocks (401/403), rate-limits (429), 5xx, TLS
     * hiccups and timeouts — is KEPT, so a valid authoritative source (e.g.
     * Britannica/Olympics that refuse or throttle our server) is never wrongly
     * stripped. Cached per request.
     */
    private function linkIsUseful(string $url, string $subject, array &$cache): bool
    {
        if (array_key_exists($url, $cache)) {
            return $cache[$url];
        }

        // SSRF guard: the URL comes from AI output (untrusted). Only fetch public
        // http(s) hosts — never loopback/link-local/private/reserved IPs (which
        // would let a crafted link probe internal services or cloud metadata).
        // A non-public or unresolvable link is simply not a valid reference.
        if (! $this->isPublicHttpUrl($url)) {
            return $cache[$url] = false;
        }

        // Try up to twice (a second, longer attempt rescues a transient blip or a
        // slow host) before giving up on a server error / timeout.
        $good = false;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $resp = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; TakeOneBot/1.0; +https://takeone.bh)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en,ar;q=0.8',
                ])
                    // Don't auto-follow redirects — a public URL could 30x to an
                    // internal address, bypassing the SSRF check above.
                    ->withOptions(['allow_redirects' => false])
                    ->connectTimeout(5)->timeout($attempt === 1 ? 12 : 18)->get($url);

                $status = $resp->status();
                if (in_array($status, [404, 410], true)) {
                    return $cache[$url] = false;            // definitively gone
                }
                if ($resp->successful()) {
                    return $cache[$url] = $this->pageIsAbout($resp->body(), $subject); // read it → judge
                }
                if (in_array($status, [401, 403, 429], true)) {
                    return $cache[$url] = true;             // page EXISTS but blocks/limits our crawler → keep
                }
                if ($status >= 300 && $status < 400) {
                    return $cache[$url] = true;             // redirect (not followed for SSRF) → keep
                }
                // 5xx / other → the page isn't serving content; retry, then drop.
            } catch (\Throwable $e) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'could not resolve') || str_contains($msg, 'resolve host')
                    || str_contains($msg, 'name or service not known') || str_contains($msg, 'connection refused')) {
                    return $cache[$url] = false;            // dead host → drop
                }
                // Timeout / TLS / other transient → retry, then drop.
            }
        }

        // Two attempts and still a server error / timeout: no usable content → drop.
        return $cache[$url] = false;
    }

    /**
     * Does this HTML page contain real content that is about $subject? We strip
     * tags, require a meaningful amount of text (guards empty/placeholder pages),
     * then look for the subject term — the full phrase, its spaced-out/collapsed
     * form (MuayThai ~ muay thai), or all of its significant words present.
     */
    private function pageIsAbout(string $html, string $subject): bool
    {
        // Title gets extra weight — most reference pages name the topic there.
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = $m[1];
        }

        $text = html_entity_decode(strip_tags($title.' '.$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(preg_replace('/\s+/', ' ', $text));

        // Guard against empty / placeholder / JS-shell pages.
        if (mb_strlen(trim($text)) < 400) {
            return false;
        }

        // Subject may arrive as "Muay Thai — Style"; match on the base name only.
        $name = mb_strtolower(trim(preg_split('/[—–\-|:(]/u', $subject)[0] ?? $subject));
        if ($name === '') {
            return true; // nothing to match against — don't over-filter
        }

        if (str_contains($text, $name)) {
            return true;
        }

        // Collapse spaces/hyphens on both sides: "muaythai" ~ "muay thai".
        $collapsedText = preg_replace('/[\s\-]+/', '', $text);
        $collapsedName = preg_replace('/[\s\-]+/', '', $name);
        if ($collapsedName !== '' && str_contains($collapsedText, $collapsedName)) {
            return true;
        }

        // Fallback: every significant word of the name appears somewhere.
        $words = array_filter(explode(' ', $name), fn ($w) => mb_strlen($w) >= 3);
        if ($words) {
            foreach ($words as $w) {
                if (! str_contains($text, $w)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * SSRF allow-check for an outbound link fetch. True only when the URL is a
     * plain http(s) URL whose host resolves EXCLUSIVELY to public IP addresses.
     * Rejects literal or resolved loopback/link-local/private/reserved IPs
     * (incl. cloud-metadata 169.254.169.254), and unresolvable hosts.
     */
    private function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return false;
        }
        // Strip IPv6 brackets, e.g. [::1].
        $host = trim($host, '[]');

        // Resolve to the set of IPs the host points at (literal IP or DNS).
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = @gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $rec) {
                if (! empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            return false; // can't resolve → don't fetch
        }

        // Every resolved IP must be public — one internal address is enough to block.
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /** Rate-limit / quota / transient errors worth retrying on the local fallback. */
    private function isRetryableProviderError(string $raw): bool
    {
        $low = strtolower($raw);
        foreach (['quota', 'rate limit', 'rate-limit', 'exceeded', 'resource_exhausted', 'too many requests', '429', 'timed out', 'timeout', 'temporarily', 'unavailable', '503', '500', 'overloaded'] as $needle) {
            if (str_contains($low, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Turn raw text-provider errors into a clear, actionable message for the admin. */
    private function friendlyTextError(string $raw): string
    {
        $low = strtolower($raw);
        if (str_contains($low, 'quota') || str_contains($low, 'rate limit') || str_contains($low, 'rate-limit') || str_contains($low, 'exceeded') || str_contains($low, 'resource_exhausted') || str_contains($low, 'too many requests') || str_contains($low, '429')) {
            return 'The AI provider is rate-limited right now (quota exhausted). Wait a minute and try again, or enable billing / switch the text provider under Admin → AI Providers.';
        }
        if (str_contains($low, 'timed out') || str_contains($low, 'timeout')) {
            return 'The AI request timed out. Please try again.';
        }
        if (str_contains($low, 'api key') || str_contains($low, 'unauthorized') || str_contains($low, 'invalid') && str_contains($low, 'key')) {
            return 'The AI provider rejected the request (check the API key under Admin → AI Providers).';
        }

        return 'AI request failed: '.$raw;
    }

    /** Turn raw provider errors into a clear, actionable message for the admin. */
    private function friendlyImageError(string $raw): string
    {
        $low = strtolower($raw);
        if (str_contains($low, 'quota') || str_contains($low, 'limit: 0') || str_contains($low, 'billing') || str_contains($low, 'free_tier')) {
            return 'Image generation is blocked on the free tier. Enable billing for your Gemini/Google Cloud project (or add an OpenAI image provider) to generate images. Text and voice keep working without billing.';
        }
        if (str_contains($low, 'no image provider')) {
            return $raw;
        }

        return 'Image generation failed: '.$raw;
    }

    /** The research/authoring brief the AI agent follows. */
    private function contentSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a sports-knowledge writer for a martial-arts and sports platform. For the given sport/martial art, write a rich, accurate, well-structured profile in BOTH English and Arabic.

Return ONE JSON object and nothing else, with exactly these keys:
{
  "name_ar": "the activity name in Arabic",
  "description_en": "<html>",
  "description_ar": "<html>",
  "image_prompt": "one English AI image prompt"
}

Rules for description_en and description_ar (convey the SAME information in each language):
- Use semantic HTML only: <h3> for section headings (each starting with a relevant emoji), <p> for paragraphs, <ul><li> for bullets, <strong>/<em> for emphasis, and <a href="..."> for links. NO inline styles, scripts, images, or classes. Wrap the Arabic version's whole content in <div dir="rtl">...</div>.
- Include these sections IN THIS ORDER, with emoji headings:
  1. 📜 Origins & Story — 2-3 full paragraphs: origin, founding figures, key dates/institutions, and how it evolved into its modern form (and, if this is a specific style/federation, how/why it diverged from siblings).
  2. 🎯 What It Focuses On — 1-2 paragraphs: core techniques, philosophy, and what distinguishes it.
  3. 💪 Benefits — 5-6 <li> bullets: physical, mental and social benefits (note when supported by research).
  4. ⚠️ Limitations — 4-5 <li> bullets: what it lacks vs other activities.
  5. 📋 Rules in Brief — 6-8 <li> bullets: scoring, match structure, prohibited actions, categories, governing body.
  6. 🔗 Trusted Resources — a <ul> of 4-6 <li> links to AUTHORITATIVE sources only (official federation, Olympics.com, Britannica, Wikipedia, reputable health/sports bodies). LINK RULES: (a) use only real, well-known URLs you are confident currently exist; (b) prefer STABLE canonical pages — the official federation homepage, the Wikipedia ARTICLE url (https://en.wikipedia.org/wiki/<Topic>), the Britannica topic page, the Olympics.com sport page — NOT deep, dated, or query-string links that rot; (c) do NOT link deep PubMed/DOI/PDF pages or specific study urls (they 404 or gate) — cite the organisation's main page instead; (d) never invent, guess, or pad citations. Fewer solid links beat more broken ones. (Every link is fetched and READ server-side after generation: any that 404, are empty, or whose page is not actually about this activity is stripped — so a broken or off-topic URL just wastes a slot.)
- Friendly, easy to read, factually accurate. Do not fabricate facts, dates or sources — if unsure, stay general rather than inventing specifics.

ARABIC QUALITY — description_ar and name_ar MUST be publication-grade Modern Standard Arabic (الفصحى). This is the top priority; broken or machine-translated Arabic is unacceptable:
- COMPOSE natively in Arabic. Do NOT translate the English word-for-word. Write each section as a skilled Arabic sports journalist would write it from scratch, then make sure it carries the same facts as the English. Rephrase freely so the Arabic reads naturally — sentence structure, word order and idioms should be Arabic, never a mirror of the English.
- Grammar (النحو والصرف): correct case endings, verb conjugation, and full gender/number agreement between nouns, adjectives, verbs and pronouns. Use sound sentence structure (الجملة الاسمية/الفعلية) and correct particles (حروف الجر والعطف).
- Spelling (الإملاء): correct hamza forms (أ، إ، ء، ئ، ؤ), and correctly distinguish ة/ه, ى/ي, and ال التعريف. No typos.
- Terminology: use the ESTABLISHED, commonly-accepted Arabic term for each sports/martial-arts concept. For proper names (people, federations, styles), give the accurate Arabic transliteration; when a Latin name is essential, you may add it in parentheses once, e.g. الآيكيدو (Aikido).
- Style: clear, cohesive, and flowing — well-connected sentences with natural collocations (تلازم لفظي سليم). Avoid awkward literal phrasings, redundant words, and English syntax leaking into the Arabic.
- PROOFREAD before returning: silently re-read the entire Arabic output end-to-end and FIX every grammatical, spelling, agreement, or word-choice error, and any phrase that a native speaker would find unnatural. Only output Arabic you are confident is correct and idiomatic.
- Keep the exact same section order, emojis, HTML structure and factual content as the English version.

Rules for image_prompt: one vivid cinematic prompt in this exact style — "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two {ACTIVITY} athletes — a muscular male and a fierce female — mid-action performing an iconic technique, wearing attire authentic to the sport, with glowing energy effects; behind them a translucent mythic creature symbolic of the sport's home culture, an iconic landmark/cityscape of its origin, bold 3D title text '{ACTIVITY}' at the top; moody chiaroscuro lighting, rim-lit silhouettes, a cohesive palette, hyper-detailed digital painting, 4K premium game cover art quality."

Output strictly the JSON object. No markdown fences, no commentary.
PROMPT;
    }

    /** Extract the first balanced JSON object from a model reply (tolerant of fences/prose). */
    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        // Strip ```json ... ``` fences if present.
        if (preg_match('/```(?:json)?\s*(.+?)```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Store a cropped image (base64 from the takeone cropper) to a staging
     * folder and return its path. A follow-up setImage() attaches it to an
     * activity. Split in two because the shared cropper posts to one static URL.
     */
    public function uploadImageStore(Request $request)
    {
        $this->guard();

        $request->validate(['image' => ['required', 'string', 'starts_with:data:image']]);

        $path = $this->storeBase64Image($request->input('image'), 'activity-catalog/uploads', 'up_'.substr(md5(uniqid('', true)), 0, 12));
        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
        }

        return response()->json(['success' => true, 'path' => $path, 'url' => asset('storage/'.$path)]);
    }

    /** Attach a previously-uploaded (staged) image to an activity as its hero. */
    public function setImage(Request $request, ActivityCatalog $activity)
    {
        $this->guard();

        $data = $request->validate(['path' => ['required', 'string']]);
        $src = $data['path'];

        // Only accept a path we produced in the staging folder, that still exists.
        if (! str_starts_with($src, 'activity-catalog/uploads/') || str_contains($src, '..') || ! Storage::disk('public')->exists($src)) {
            return response()->json(['success' => false, 'message' => 'Invalid image reference.'], 422);
        }

        $ext = pathinfo($src, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = 'activity-catalog/'.$activity->uuid.'/hero_'.substr(md5(uniqid('', true)), 0, 12).'.'.$ext;
        Storage::disk('public')->copy($src, $dest);
        Storage::disk('public')->delete($src); // drop the staging copy

        $old = $activity->picture_url;
        $activity->picture_url = $dest;
        $activity->save();
        if ($old && $old !== $dest && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded and attached.',
            'activity' => $this->payload($activity),
        ]);
    }

    /**
     * Generate a hero image from the entry's image_prompt via the configured
     * image provider (OpenAI Images) and attach it. Old image is replaced.
     */
    public function generateImage(Request $request, ActivityCatalog $activity, AiManager $ai)
    {
        $this->guard();

        // Prefer a prompt supplied from the editor (unsaved edits), else the stored one.
        $request->validate(['prompt' => ['nullable', 'string', 'max:4000']]);
        $prompt = trim((string) ($request->input('prompt') ?: $activity->image_prompt));
        if ($prompt === '') {
            return response()->json(['success' => false, 'message' => 'This activity has no image prompt to generate from.'], 422);
        }

        try {
            $dataUri = $ai->image()->generate($prompt);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $this->friendlyImageError($e->getMessage())], 422);
        }

        $path = $this->storeBase64Image($dataUri, 'activity-catalog/'.$activity->uuid, 'hero_'.substr(md5($prompt), 0, 8));
        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'The generated image could not be stored.'], 422);
        }

        // Replace the previous image only after a successful store.
        $old = $activity->picture_url;
        $activity->picture_url = $path;
        $activity->save();
        if ($old && $old !== $path && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image generated and attached.',
            'activity' => $this->payload($activity),
        ]);
    }

    public function index(Request $request)
    {
        $this->guard();

        $activities = ActivityCatalog::query()
            ->orderBy('name')
            ->get();

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.platform.mobile.activities');

        return view($mobile ? 'admin.platform.mobile.activities' : 'admin.platform.activities', compact('activities'));
    }

    public function store(Request $request)
    {
        $this->guard();

        $data = $this->validated($request);
        $entry = new ActivityCatalog;
        $this->fill($entry, $data);
        $entry->save();

        return response()->json([
            'success' => true,
            'message' => 'Activity added to the directory.',
            'activity' => $this->payload($entry),
        ]);
    }

    public function update(Request $request, ActivityCatalog $activity)
    {
        $this->guard();

        $data = $this->validated($request, $activity);
        $this->fill($activity, $data);
        $activity->save();

        return response()->json([
            'success' => true,
            'message' => 'Activity updated.',
            'activity' => $this->payload($activity),
        ]);
    }

    public function destroy(ActivityCatalog $activity)
    {
        $this->guard();

        $activity->delete();

        return response()->json(['success' => true, 'message' => 'Activity removed from the directory.']);
    }

    /* ----------------------------------------------------------------- */

    private function validated(Request $request, ?ActivityCatalog $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'description_ar' => ['nullable', 'string', 'max:20000'],
            'image_prompt' => ['nullable', 'string', 'max:4000'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^bi-[a-z0-9\-]+$/'],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*.name' => ['nullable', 'string', 'max:100'],
            'variants.*.name_ar' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function fill(ActivityCatalog $entry, array $data): void
    {
        $entry->name = trim($data['name']);
        $entry->icon = $data['icon'] ?? null;
        $entry->is_active = (bool) ($data['is_active'] ?? true);
        $entry->image_prompt = filled($data['image_prompt'] ?? null) ? trim($data['image_prompt']) : null;
        $entry->description = filled($data['description'] ?? null)
            ? HtmlSanitizer::clean($data['description'])
            : null;

        // Translations (Arabic) — pruned to null when empty by the trait.
        $entry->setTranslation('name', 'ar', $data['name_ar'] ?? null);
        $entry->setTranslation('description', 'ar', filled($data['description_ar'] ?? null)
            ? HtmlSanitizer::clean($data['description_ar'])
            : null);

        // Styles / federations — rebuild the list from the submitted rows,
        // dropping blanks and de-duping by name (case-insensitive).
        $variants = [];
        $seen = [];
        foreach ($data['variants'] ?? [] as $row) {
            $name = trim(strip_tags((string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ar = trim(strip_tags((string) ($row['name_ar'] ?? '')));
            $variants[] = array_filter([
                'name' => $name,
                'name_ar' => $ar !== '' ? $ar : null,
            ], fn ($v) => $v !== null);
        }
        $entry->variants = $variants ?: null;
    }

    private function payload(ActivityCatalog $entry): array
    {
        return [
            'uuid' => $entry->uuid,
            'name' => $entry->name,
            'name_ar' => $entry->tr('name', 'ar') === $entry->name ? '' : $entry->tr('name', 'ar'),
            'slug' => $entry->slug,
            'description' => $entry->description,
            'description_ar' => data_get($entry->translations, 'description.ar', ''),
            'image_prompt' => $entry->image_prompt,
            'icon' => $entry->icon,
            'is_active' => (bool) $entry->is_active,
            'usage_count' => (int) $entry->usage_count,
            'variants' => $entry->variants ?: [],
            'picture_src' => $entry->picture_url ? asset('storage/'.$entry->picture_url) : null,
            'has_prompt' => filled($entry->image_prompt),
            'update_url' => route('admin.platform.activities.update', $entry),
            'image_url' => route('admin.platform.activities.image', $entry),
            'set_image_url' => route('admin.platform.activities.set-image', $entry),
            'destroy_url' => route('admin.platform.activities.destroy', $entry),
        ];
    }
}

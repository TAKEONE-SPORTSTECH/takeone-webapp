<?php

namespace App\Events\Support;

use App\Events\Models\EventVisit;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * How many REAL PEOPLE have opened an event's page, and who they were.
 *
 * The one place that decides three things, because each of them was going to be
 * decided differently in two places otherwise:
 *
 *   1. what counts as a person rather than a machine;
 *   2. what identifies one visitor across two page loads;
 *   3. what an organiser is shown about them.
 *
 * ── 1. Machines ─────────────────────────────────────────────────────────────
 *
 * The organiser asked for "the count of the real people", and on a shared link
 * most of the traffic is not people at all. Paste an event link into WhatsApp
 * and WhatsApp fetches the page to draw the preview card; so do Facebook,
 * Telegram, Slack, Twitter and iMessage. Then the crawlers, then whatever is
 * monitoring the site. A raw hit counter on a link shared into three group
 * chats reads several hundred before one human has opened it.
 *
 * So a machine is recognised and recorded as one, and never counted. It is
 * still recorded, rather than dropped, so the panel can say how many were left
 * out — a number an organiser is asked to trust is easier to trust when the
 * thing it excluded is also on the screen.
 *
 * ⚠️ This is a HEURISTIC on a header the client chooses, and it can only ever
 * be. It is not a security boundary and nothing is authorised by it: a scraper
 * that lies about its user agent is counted as a person, which is a wrong
 * number and not an open door. Deny-by-default is the wrong instinct here —
 * treating an unrecognised agent as a bot would silently discard real visitors
 * on browsers nobody has heard of, which is the failure the organiser would
 * never see.
 *
 * ── 2. One visitor ──────────────────────────────────────────────────────────
 *
 * A signed-in visitor is their account. Everybody else gets a first-party
 * cookie holding a random token — a token this application minted, not a
 * fingerprint taken from them — and the row is keyed on a digest of it. With no
 * cookie (the first request, or a browser that refuses them) the key falls back
 * to a digest of the request's own shape, hashed with the app key so it cannot
 * be recomputed outside this application and cannot be turned back into an
 * address.
 *
 * ⚠️ A BROWSER IS HOW A ROW IS RECORDED; A PERSON IS HOW IT IS READ.
 *
 * Recording has to be per browser — it is the only thing there is to go on for
 * somebody with no account. But an organiser asked for one PERSON to be one
 * row, and they were right: a member who opened the poster on their phone at
 * the gym and again on a laptop at home appeared five times in the list, which
 * made the count a browser count wearing a person's name (reported
 * 2026-09-10).
 *
 * So every row belonging to the same ACCOUNT is folded into one before anybody
 * sees it: the visits add up, the first and last dates stretch to cover them
 * all, and the devices and languages become the set of everything they used.
 * `mergeByPerson()` is the one place that happens, and both the count and the
 * list go through it — a headline that disagreed with the list under it would
 * be worse than either number alone.
 *
 * A SIGNED-OUT visitor is still one row per browser, and that is the honest
 * limit rather than a shortcut: without an account there is nothing that says
 * two browsers are one person, and the alternative — a durable fingerprint —
 * is worse for the visitor and still guesses. The panel says so.
 *
 * ── 3. What the organiser sees ──────────────────────────────────────────────
 *
 * Three kinds, which is what was asked for:
 *
 *   participant  signed in, and holds a registration for THIS event
 *   member       signed in, no registration — someone who looked and did not enter
 *   visitor      not signed in
 *
 * A name is only ever shown for a signed-in visitor, because that is the only
 * one there is a name for. An anonymous visitor is a row saying when they came,
 * on what kind of screen, in what language and from where the link was shared —
 * and nothing that could identify them.
 */
class EventVisitors
{
    /** The cookie holding this browser's own random token. */
    public const COOKIE = 'takeone_visitor';

    /** How long before a returning visitor is counted as a fresh visit. */
    private const REVISIT_MINUTES = 30;

    /**
     * How many of one person's visits are remembered individually.
     *
     * Capped deliberately. An organiser wants to know that somebody came back
     * three times last week; nobody needs a complete attendance record of a
     * stranger reading a poster, and an uncapped list would become one.
     */
    private const REMEMBER_VISITS = 12;

    /**
     * Agents that are not people.
     *
     * Ordered so the NAMED ones match first — an organiser reading "WhatsApp
     * link preview" learns something; "bot" does not. The generic catch-alls at
     * the end are what keep the list from needing to be complete.
     *
     * @var array<string, string> needle (lowercase) => what to call it
     */
    private const AGENTS = [
        // The share-sheet previewers. On a link posted into group chats these
        // are the overwhelming majority of the traffic.
        'whatsapp' => 'WhatsApp preview',
        'facebookexternalhit' => 'Facebook preview',
        'facebookcatalog' => 'Facebook preview',
        'instagram' => 'Instagram preview',
        'twitterbot' => 'X preview',
        'telegrambot' => 'Telegram preview',
        'slackbot' => 'Slack preview',
        'slack-imgproxy' => 'Slack preview',
        'discordbot' => 'Discord preview',
        'linkedinbot' => 'LinkedIn preview',
        'skypeuripreview' => 'Skype preview',
        'viber' => 'Viber preview',
        'line/' => 'LINE preview',
        'snapchat' => 'Snapchat preview',
        'pinterest' => 'Pinterest preview',
        'redditbot' => 'Reddit preview',
        'embedly' => 'Link preview',
        'quora link preview' => 'Link preview',
        'vkshare' => 'Link preview',
        'w3c_validator' => 'Validator',
        'applebot' => 'Apple crawler',

        // Search and AI crawlers.
        'googlebot' => 'Google crawler',
        'google-inspectiontool' => 'Google crawler',
        'adsbot-google' => 'Google crawler',
        'mediapartners-google' => 'Google crawler',
        'bingbot' => 'Bing crawler',
        'yandexbot' => 'Yandex crawler',
        'duckduckbot' => 'DuckDuckGo crawler',
        'baiduspider' => 'Baidu crawler',
        'gptbot' => 'AI crawler',
        'oai-searchbot' => 'AI crawler',
        'chatgpt-user' => 'AI crawler',
        'claudebot' => 'AI crawler',
        'anthropic-ai' => 'AI crawler',
        'perplexitybot' => 'AI crawler',
        'ccbot' => 'AI crawler',
        'bytespider' => 'AI crawler',
        'amazonbot' => 'AI crawler',
        'semrushbot' => 'SEO crawler',
        'ahrefsbot' => 'SEO crawler',
        'mj12bot' => 'SEO crawler',
        'dotbot' => 'SEO crawler',
        'petalbot' => 'SEO crawler',

        // Monitors and scripts — "servers and gateways", in the organiser's words.
        'uptimerobot' => 'Uptime monitor',
        'pingdom' => 'Uptime monitor',
        'statuscake' => 'Uptime monitor',
        'betteruptime' => 'Uptime monitor',
        'newrelicpinger' => 'Uptime monitor',
        'site24x7' => 'Uptime monitor',
        'curl/' => 'Script',
        'wget' => 'Script',
        'python-requests' => 'Script',
        'python-urllib' => 'Script',
        'go-http-client' => 'Script',
        'okhttp' => 'Script',
        'axios' => 'Script',
        'node-fetch' => 'Script',
        'guzzlehttp' => 'Script',
        'java/' => 'Script',
        'apache-httpclient' => 'Script',
        'libwww-perl' => 'Script',
        'postmanruntime' => 'Script',
        'headlesschrome' => 'Automated browser',
        'phantomjs' => 'Automated browser',
        'puppeteer' => 'Automated browser',
        'playwright' => 'Automated browser',
        'lighthouse' => 'Automated browser',
        'chrome-lighthouse' => 'Automated browser',

        // The catch-alls, last.
        'bot/' => 'Automated',
        'bot;' => 'Automated',
        'bot)' => 'Automated',
        ' bot' => 'Automated',
        'crawler' => 'Automated',
        'spider' => 'Automated',
        'scrapy' => 'Automated',
        'fetcher' => 'Automated',
        'monitoring' => 'Automated',
        'preview' => 'Automated',
    ];

    /**
     * Record this request as a visit, if it is one.
     *
     * Best-effort throughout: a public page must render whatever this does, so
     * every failure is swallowed. A missing visit is a wrong number; a 500 on
     * an event poster the morning of a competition is somebody's day.
     */
    public function record(ClubEvent $event, Request $request, ?string $token = null): void
    {
        try {
            // Only a person LOOKING at the page. A prefetch, a JSON re-read or
            // a HEAD (which is how most previewers check a link) is not a visit.
            if (! $request->isMethod('GET') || $request->expectsJson()) {
                return;
            }
            if (in_array(strtolower((string) $request->header('Purpose')), ['prefetch', 'preview'], true)
                || strtolower((string) $request->header('Sec-Purpose')) !== ''
                || $request->header('X-Moz') === 'prefetch') {
                return;
            }

            $agent = (string) $request->userAgent();
            $bot = $this->botName($agent);

            $key = $this->visitorKey($request, $token);

            if ($key === null) {
                return;
            }

            $now = now();

            // The row, or a new one. `updateOrCreate` on the unique pair, so two
            // simultaneous first views cannot make two rows for one person.
            $visit = EventVisit::firstOrNew([
                'event_id' => $event->id,
                'visitor_key' => $key,
            ]);

            if (! $visit->exists) {
                $visit->fill([
                    'is_bot' => $bot !== null,
                    'bot_name' => $bot,
                    'first_seen_at' => $now,
                    'visits' => 0,
                ]);
            }

            // A reload is not a new visit; coming back tomorrow is. Without
            // this, a page left open on a refresh loop is a hundred visits.
            $fresh = $visit->last_seen_at === null
                || $visit->last_seen_at->lt($now->copy()->subMinutes(self::REVISIT_MINUTES));

            if (! $fresh && $visit->exists && $visit->user_id === ($request->user()?->id)) {
                return;
            }

            if ($fresh) {
                $visit->visits = (int) $visit->visits + 1;

                // The last few, newest first, and the tail dropped as it goes.
                $recent = is_array($visit->recent_visits) ? $visit->recent_visits : [];
                array_unshift($recent, [
                    'at' => $now->toIso8601String(),
                    'device' => $this->device($agent),
                    'locale' => substr((string) app()->getLocale(), 0, 12),
                    'from' => $this->referrerHost($request),
                ]);
                $visit->recent_visits = array_slice($recent, 0, self::REMEMBER_VISITS);
            }

            // Signing in AFTER visiting anonymously upgrades the row rather than
            // making a second one — which is what links a visitor to the entry
            // they then submitted.
            if ($user = $request->user()) {
                $visit->user_id = $user->id;
            }

            $visit->last_seen_at = $now;
            $visit->device = $this->device($agent);
            $visit->locale = substr((string) app()->getLocale(), 0, 12);
            $visit->referrer_host = $this->referrerHost($request);
            $visit->save();
        } catch (\Throwable $e) {
            // Counted or not, the page renders.
        }
    }

    /** What to call the machine behind this agent, or null for a person. */
    public function botName(string $agent): ?string
    {
        $agent = strtolower(trim($agent));

        // No agent at all is not a browser. Every real one sends something.
        if ($agent === '') {
            return 'Unidentified';
        }

        foreach (self::AGENTS as $needle => $label) {
            if (str_contains($agent, $needle)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * The headline, and the two numbers that make it believable.
     *
     * @return array{people: int, participants: int, members: int, anonymous: int, excluded: int, returning: int, views: int}
     */
    public function summary(ClubEvent $event): array
    {
        $rows = EventVisit::where('event_id', $event->id)
            ->select('is_bot', 'user_id', 'visits')
            ->get();

        $entrants = $this->entrantUserIds($event);

        // One PERSON per row before anything is counted — see the note at the
        // top of this class. Counting the raw rows made a member with a phone
        // and a laptop two people.
        $people = $this->mergeByPerson($rows->where('is_bot', false));

        return [
            'people' => count($people),
            'participants' => count(array_filter($people, fn ($p) => $p['user_id'] && isset($entrants[$p['user_id']]))),
            'members' => count(array_filter($people, fn ($p) => $p['user_id'] && ! isset($entrants[$p['user_id']]))),
            'anonymous' => count(array_filter($people, fn ($p) => ! $p['user_id'])),
            'returning' => count(array_filter($people, fn ($p) => $p['visits'] > 1)),
            'views' => array_sum(array_column($people, 'visits')),
            'excluded' => $rows->where('is_bot', true)->count(),
        ];
    }

    /**
     * Fold the rows of one account together.
     *
     * Keyed by user id where there is one, and by the row itself where there is
     * not — so an account is one entry however many browsers it signed in from,
     * and a signed-out visitor stays their own.
     *
     * @param  \Illuminate\Support\Collection<int, EventVisit>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mergeByPerson($rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $key = $row->user_id ? 'u'.$row->user_id : 'k'.$row->getKey();

            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'user_id' => $row->user_id,
                    'rows' => [$row],
                    'visits' => (int) $row->visits,
                ];

                continue;
            }

            $merged[$key]['rows'][] = $row;
            $merged[$key]['visits'] += (int) $row->visits;
        }

        return array_values($merged);
    }

    /**
     * Who they were, newest first.
     *
     * Bounded: an organiser reading a list does not read three thousand rows,
     * and an unbounded list on a public event is a query somebody will make a
     * hobby of.
     *
     * @return array<int, array<string, mixed>>
     */
    public function details(ClubEvent $event, int $limit = 200): array
    {
        $entrants = $this->entrantUserIds($event);

        $rows = EventVisit::where('event_id', $event->id)
            ->people()
            ->with(['user:id,uuid,full_name,name,profile_picture,profile_picture_is_public,updated_at'])
            ->orderByDesc('last_seen_at')
            // Bounded: an organiser reading a list does not read three thousand
            // rows, and an unbounded query on a public event is one somebody
            // will make a hobby of. Read before folding, so the cap is on rows
            // rather than on people — a member with four browsers must not cost
            // three other visitors their place in the list.
            ->limit($limit * 2)
            ->get();

        $people = array_map(function (array $person) use ($entrants) {
            /** @var array<int, EventVisit> $group */
            $group = $person['rows'];
            $first = $group[0];
            $user = $first->user;

            $role = $user === null ? 'visitor'
                : (isset($entrants[$user->id]) ? 'participant' : 'member');

            // One person's whole trail, newest first, across every browser they
            // used — then capped again, because three devices would otherwise
            // hand back three times the list.
            $trail = [];
            foreach ($group as $row) {
                foreach ((array) ($row->recent_visits ?? []) as $t) {
                    if (is_array($t) && ! empty($t['at'])) {
                        $trail[] = $t;
                    }
                }
            }
            usort($trail, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
            $trail = array_slice($trail, 0, self::REMEMBER_VISITS);

            $seen = fn (string $field, bool $max) => collect($group)
                ->pluck($field)->filter()
                ->sort()->{$max ? 'last' : 'first'}();

            // Every kind of screen and every language they read it in — the set,
            // not the last one, which is the point of folding the rows.
            $set = fn (string $field) => collect($group)->pluck($field)
                ->filter()->unique()->values()->all();

            return [
                // The person's identity for the client — never a visitor key.
                'id' => $person['user_id'] ? 'u'.$person['user_id'] : 'k'.$first->getKey(),
                'role' => $role,
                /*
                 * A HANDLE for somebody there is no name for. Two anonymous
                 * rows that both read "Visitor" are two rows an organiser
                 * cannot tell apart, refer to, or follow down the list.
                 * Derived from a hash OF the visitor key, never the key itself.
                 */
                'tag' => $this->tag($first->visitor_key),
                'hue' => $this->hue($first->visitor_key),
                'name' => $user?->full_name ?: $user?->name,
                // The member's own public page — the uuid door, never a numeric id.
                'uuid' => $user?->uuid,
                'photo' => $this->photo($user),
                'entry_role' => $entrants[$user?->id] ?? null,
                'devices' => $set('device'),
                'locales' => $set('locale'),
                // How many browsers this one person used. Said plainly, because
                // "three visits on two devices" is the honest shape of it.
                'browsers' => count($group),
                'from' => collect($group)->pluck('referrer_host')->filter()->first(),
                'visits' => $person['visits'],
                'first_seen' => optional($seen('first_seen_at', false))->toIso8601String(),
                'last_seen' => optional($seen('last_seen_at', true))->toIso8601String(),
                'trail' => $trail,
            ];
        }, $this->mergeByPerson($rows));

        // Newest first again: folding reordered nothing, but a merged person's
        // last visit may be later than the row that led the query.
        usort($people, fn ($a, $b) => strcmp((string) $b['last_seen'], (string) $a['last_seen']));

        return array_slice($people, 0, $limit);
    }

    /**
     * The user ids holding a registration for this event, and in what role.
     *
     * @return array<int, string>
     */
    private function entrantUserIds(ClubEvent $event): array
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->whereNotNull('user_id')
            ->pluck('role', 'user_id')
            ->map(fn ($r) => (string) ($r ?: 'participant'))
            ->all();
    }

    /**
     * Their face, only if they publish it.
     *
     * `profile_picture_is_public` is the member's own choice about who may see
     * it. An organiser's visitor list is not the member's own club, so a false
     * here means no picture — and the absence is silent, exactly as it is on the
     * hall screens (see BracketView::photo).
     */
    private function photo(mixed $user): ?string
    {
        if (! $user || ! $user->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return file_url($user->profile_picture).'?v='.optional($user->updated_at)->timestamp;
    }

    /**
     * This browser's key.
     *
     * The cookie's token first — minted by this application, so it identifies a
     * browser without taking anything from the visitor. Failing that, a digest
     * of the request's own shape, salted with the app key: enough to stop one
     * person counting twice within a page load, impossible to reverse, and
     * useless outside this application.
     */
    private function visitorKey(Request $request, ?string $token = null): ?string
    {
        $token = $token ?: (string) $request->cookie(self::COOKIE);

        if ($token !== '') {
            return hash('sha256', 'v1|'.$token.'|'.config('app.key'));
        }

        $ip = (string) $request->ip();

        if ($ip === '') {
            return null;
        }

        return hash('sha256', 'v1|'.$ip.'|'.$request->userAgent().'|'.config('app.key'));
    }

    /**
     * A short, stable handle for one visitor row.
     *
     * A hash OF the digest, so nothing about the visitor key leaks even to the
     * organiser — and stable, so the same person carries the same badge on
     * every visit and between two openings of the panel.
     */
    private function tag(string $key): string
    {
        return strtoupper(substr(hash('crc32b', 'tag|'.$key), 0, 3));
    }

    /** A colour from the same digest, so the badge is recognisable at a glance. */
    private function hue(string $key): int
    {
        return (int) (hexdec(substr(hash('crc32b', 'hue|'.$key), 0, 4)) % 360);
    }

    /** A fresh token for a browser that has none. */
    public function newToken(): string
    {
        return Str::random(32);
    }

    private function device(string $agent): string
    {
        $a = strtolower($agent);

        return match (true) {
            str_contains($a, 'ipad') || str_contains($a, 'tablet') => 'tablet',
            str_contains($a, 'mobi') || str_contains($a, 'iphone') || str_contains($a, 'android') => 'mobile',
            default => 'desktop',
        };
    }

    /**
     * Where the link was shared, as a HOST.
     *
     * Never the path: which Instagram post carried the link is not something
     * this needs, and the rest of a referring URL is the visitor's business.
     * Our own host is dropped — a click from the event's own page is not a
     * source.
     */
    private function referrerHost(Request $request): ?string
    {
        $ref = (string) $request->headers->get('referer');

        if ($ref === '') {
            return null;
        }

        $host = parse_url($ref, PHP_URL_HOST);

        if (! is_string($host) || $host === '' || $host === $request->getHost()) {
            return null;
        }

        return substr(preg_replace('/^www\./', '', strtolower($host)), 0, 120);
    }
}

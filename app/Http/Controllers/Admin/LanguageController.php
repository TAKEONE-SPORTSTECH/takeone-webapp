<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Translation\Jobs\TranslateInterfaceLocale;
use App\Translation\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The platform's own languages — what each one covers, and the power to fix any
 * word of it.
 *
 * ── Why this screen exists ──────────────────────────────────────────────────
 *
 * The interface used to live in `lang/<code>/*.php`, which meant three things
 * that were all invisible from inside the product: nobody could see how much of
 * a language was actually translated, nobody could correct a word without a
 * deploy, and eleven generated languages were untracked files one rsync away
 * from deletion. The words are rows now (interface_translations), and this is
 * where a person meets them.
 *
 * ⚠️ SUPER-ADMIN ONLY, and deliberately so. This screen spends money — a run is
 * ~5,500 strings at a paid provider — and it rewrites what every user of the
 * platform reads. It is not club-scoped and never should be: these are the
 * PRODUCT's words, not a tenant's.
 */
class LanguageController extends Controller
{
    /** The languages, and how far each has got. */
    public function index(Request $request): View
    {
        $tier = $this->tier($request);

        $coverage = collect(Translations::interfaceCoverage($tier))->keyBy('locale');
        $locales = Translations::locales();

        $rows = [];

        foreach ($locales->all() as $code => $meta) {
            if ($code === 'en') {
                continue;   // the source; it has no coverage to report
            }

            $have = $coverage->get($code);

            $rows[] = [
                'code' => $code,
                'name' => $meta['name'],
                'native' => $meta['native'],
                'dir' => $meta['dir'] ?? 'ltr',
                'flag' => $locales->flag($code),
                'stored' => (int) ($have['stored'] ?? 0),
                'expected' => (int) ($have['expected'] ?? 0),
                'human' => (int) ($have['human'] ?? 0),
                'missing' => (int) ($have['missing'] ?? 0),
                'interface' => $locales->isInterfaceLocale($code),
                'run' => TranslateInterfaceLocale::status($code),
            ];
        }

        // Furthest along first — the ones a reader could actually be served.
        usort($rows, fn ($a, $b) => $b['stored'] <=> $a['stored']);

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.languages.mobile');

        return view($mobile ? 'admin.languages.mobile' : 'admin.languages.index', [
            'languages' => $rows,
            'tier' => $tier,
        ]);
    }

    /**
     * One language's strings, beside the English they came from.
     *
     * Paged, because a tier is five and a half thousand strings and no screen
     * and no JSON payload wants all of them at once.
     */
    public function show(Request $request, string $locale): JsonResponse
    {
        $locale = $this->locale($locale);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'only' => ['nullable', 'in:all,missing,human'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json(Translations::interfaceStrings(
            $locale,
            $this->tier($request),
            $data['q'] ?? null,
            $data['only'] ?? 'all',
            (int) ($data['page'] ?? 1),
        ) + ['locale' => $locale]);
    }

    /**
     * Correct one string.
     *
     * Stored as a PERSON's words, which no machine run will overwrite — the
     * same invariant the content translations carry, and the reason making a
     * correction is worth the effort.
     */
    public function update(Request $request, string $locale): JsonResponse
    {
        $locale = $this->locale($locale);

        $data = $request->validate([
            'file' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:191'],
            // Empty means "drop my correction", not "store a blank".
            'value' => ['nullable', 'string', 'max:4000'],
        ]);

        $saved = Translations::correctInterface($locale, $data['file'], $data['key'], $data['value']);

        return response()->json([
            'success' => $saved,
            'message' => __('shared.saved'),
            'entry' => [
                'file' => $data['file'],
                'key' => $data['key'],
                'value' => $data['value'],
                'origin' => filled($data['value']) ? 'human' : null,
            ],
        ]);
    }

    /**
     * Translate everything this language is still missing.
     *
     * ⚠️ Queued, never inline. A tier is ~5,500 strings and between fifteen
     * minutes and an hour of API calls; a request cannot hold that open, and a
     * screen that tried would time out having spent the money anyway.
     */
    public function translate(Request $request, string $locale): JsonResponse
    {
        $locale = $this->locale($locale);

        $data = $request->validate([
            'tier' => ['nullable', 'in:event,visitor,member,staff,all'],
            // Redo strings that already exist, rather than only filling gaps.
            // A person's corrections still survive it — see InterfaceStore.
            'rewrite' => ['nullable', 'boolean'],
        ]);

        $running = TranslateInterfaceLocale::status($locale);

        if (($running['state'] ?? null) === 'running') {
            return response()->json([
                'success' => false,
                'message' => __('platform.admin_languages_already_running'),
            ], 409);
        }

        TranslateInterfaceLocale::dispatch(
            $locale,
            $data['tier'] ?? $this->tier($request),
            (bool) ($data['rewrite'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => __('platform.admin_languages_queued'),
            'run' => ['state' => 'running'],
        ]);
    }

    /** Where a run has got to. Polled by the screen; starts nothing. */
    public function status(Request $request, string $locale): JsonResponse
    {
        $locale = $this->locale($locale);

        $coverage = collect(Translations::interfaceCoverage($this->tier($request)))
            ->firstWhere('locale', $locale);

        return response()->json([
            'success' => true,
            'run' => TranslateInterfaceLocale::status($locale),
            'stored' => (int) ($coverage['stored'] ?? 0),
            'expected' => (int) ($coverage['expected'] ?? 0),
            'missing' => (int) ($coverage['missing'] ?? 0),
        ]);
    }

    /**
     * A locale this platform actually serves, or a 404.
     *
     * ⚠️ Never taken as given: it is a key into a table, a cache key and a
     * directory name. Normalised against the served list, exactly as every
     * other door into this module does it.
     */
    private function locale(string $locale): string
    {
        $normalised = Translations::locales()->normalise($locale);

        abort_if($normalised === null || $normalised === 'en', 404);

        return $normalised;
    }

    private function tier(Request $request): string
    {
        $tier = (string) $request->query('tier', 'event');

        return in_array($tier, ['event', 'visitor', 'member', 'staff', 'all'], true) ? $tier : 'event';
    }
}

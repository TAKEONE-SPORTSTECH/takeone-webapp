<?php

namespace App\Translation\Controllers;

use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Translation\Models\TranslationDocument;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\Translator;
use App\Translation\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The organiser's half: read what the machine wrote, and correct it.
 *
 * This is what makes the feature honest. An automatic translation on a public
 * poster with no way to fix it is a promise the platform cannot keep — the
 * organiser is the one who will be asked, at the venue, why the Portuguese said
 * the wrong thing. So: every language, every field, side by side with the
 * source, editable.
 *
 * ⚠️ THE ONE INVARIANT: a saved correction is `origin = human`, and no machine
 * run may ever overwrite it — not a re-translate, not an edit to the source
 * text, not a provider change. TranslateContent::store() enforces it; this
 * controller is the only thing that writes that flag.
 *
 * Authorisation is EventAccess::canManage, the same predicate the event console
 * uses. Nothing here is readable by an entrant, and the module grants nothing:
 * a person who cannot manage the event cannot see, start, edit or delete a
 * translation of it.
 */
class EventTranslationController extends Controller
{
    public function __construct(
        private Translator $translator,
        private ContentLocales $locales,
        private EventAccess $access,
    ) {}

    /**
     * Every language this event has been asked for, and how each stands.
     *
     * Also the source text itself, so the review screen can show the two
     * columns without a second request.
     */
    public function index(Request $request, string $event): JsonResponse
    {
        $event = $this->event($event);

        $document = Translator::document($event);

        // ONE row, every language (2026-09-09). What used to be a run table
        // plus a row per field per language is now a single JSON document, so
        // the review screen reads the whole thing in one query.
        $stored = $this->translator->documentFor($event);

        $languages = [];

        foreach (array_keys($stored) as $locale) {
            if (! $this->locales->has($locale)) {
                continue;   // a language that has since left the config
            }

            $languages[] = $this->summary($event, (string) $locale, $document, $stored);
        }

        // Most recently touched first — the one being worked on is the one
        // somebody wants.
        usort($languages, fn ($a, $b) => strcmp((string) $b['updated_at'], (string) $a['updated_at']));

        return response()->json([
            'success' => true,
            'source_locale' => $event->sourceLocale(),
            'source_name' => $this->locales->native($event->sourceLocale()),
            // The source text, field by field, in the order the document
            // declares — which is the order the page reads in.
            'fields' => collect($document)->map(fn ($text, $field) => [
                'field' => $field,
                'label' => $this->label($field),
                'source' => $text,
            ])->values()->all(),
            'languages' => $languages,
            // Everything on offer, for the "add a language" picker.
            'available' => collect($this->locales->all())
                ->map(fn ($m, $code) => [
                    'code' => $code,
                    'name' => $m['name'],
                    'native' => $m['native'],
                    'dir' => $m['dir'],
                    'flag' => $this->locales->flag($code),
                ])->values()->all(),
        ]);
    }

    /**
     * Save a correction.
     *
     * The saved text is stamped with the CURRENT source hash, so an organiser
     * correcting a translation of text they can see is recorded as having
     * reviewed that exact text. It is marked `human` and becomes permanent
     * until a person changes it again.
     */
    public function update(Request $request, string $event): JsonResponse
    {
        $event = $this->event($event);

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:12'],
            'field' => ['required', 'string', 'max:120'],
            // Empty means "drop my correction and let the machine's stand".
            'value' => ['nullable', 'string', 'max:6000'],
        ]);

        $locale = $this->locales->normalise($data['locale']);
        abort_if($locale === null, 422, 'Unsupported language.');

        $document = Translator::document($event);

        // The field must be one this record actually publishes. Without this,
        // the endpoint would be a way to write arbitrary rows into the store.
        abort_unless(isset($document[$data['field']]), 422, 'Unknown field.');

        $value = trim((string) ($data['value'] ?? ''));

        /*
         * One writer for corrections, shared with the MCP tool, so the two can
         * never drift: it stamps the source hash, marks the entry `human`, and
         * treats an empty value as REMOVE rather than blank.
         */
        Translations::correct($event, $locale, $data['field'], $value);

        return response()->json([
            'success' => true,
            'message' => __('translation::messages.saved'),
            'locale' => $locale,
            'field' => $data['field'],
            'value' => $value,
            'origin' => $value === '' ? TranslationDocument::MACHINE : TranslationDocument::HUMAN,
        ]);
    }

    /**
     * Translate this event into a language now, or do the stale parts again.
     *
     * The organiser's retry is not subject to the hour-long cool-off a
     * visitor's is: they have just fixed the API key, and being told to come
     * back in an hour would be absurd.
     */
    public function retranslate(Request $request, string $event): JsonResponse
    {
        $event = $this->event($event);

        $locale = $this->locales->normalise($request->input('locale'));
        abort_if($locale === null, 422, 'Unsupported language.');

        // Clear the machine's previous attempt so `ensure` has work to do —
        // the organiser asked for this again, and a cool-off that applies to a
        // stranger's page view must not apply to them.
        Translations::dropMachine($event, $locale);

        $status = $this->translator->ensure($event, $locale);

        return response()->json([
            'success' => true,
            'message' => __('translation::messages.queued'),
            'locale' => $locale,
            'status' => $status,
        ]);
    }

    /**
     * Remove a language entirely.
     *
     * Destructive and deliberate: it drops the organiser's own corrections too,
     * which is why it is a separate act with its own confirmation rather than
     * something bundled into "re-translate".
     */
    public function destroy(Request $request, string $event): JsonResponse
    {
        $event = $this->event($event);

        $locale = $this->locales->normalise($request->input('locale'));
        abort_if($locale === null, 422, 'Unsupported language.');

        Translations::remove($event, $locale);

        return response()->json([
            'success' => true,
            'message' => __('translation::messages.removed'),
            'locale' => $locale,
        ]);
    }

    /**
     * One language's state: its rows, whether each is fresh, and who wrote it.
     *
     * @param  array<string,string>  $document
     */
    /**
     * One language's state: its words, whether each is fresh, and who wrote it.
     *
     * @param  array<string,string>  $document  the event's source words
     * @param  array<string,mixed>  $stored     the whole translation document
     */
    private function summary(ClubEvent $event, string $locale, array $document, array $stored): array
    {
        $entry = TranslationDocument::entry($stored, $locale);
        $fields = $entry['fields'];

        $values = [];
        $humanCount = 0;
        $staleCount = 0;
        $translated = 0;

        foreach ($document as $field => $source) {
            $held = is_array($fields[$field] ?? null) ? $fields[$field] : null;
            $stale = $held !== null && ($held['h'] ?? null) !== Translator::hash($source);
            $human = ($held['o'] ?? null) === TranslationDocument::HUMAN;

            if ($held !== null) {
                $translated++;
            }
            if ($human) {
                $humanCount++;
            }
            if ($stale) {
                $staleCount++;
            }

            $values[] = [
                'field' => $field,
                'label' => $this->label($field),
                'source' => $source,
                'value' => $held['v'] ?? null,
                'origin' => $held['o'] ?? null,
                // A human entry that is stale is not wrong, it is UNREVIEWED —
                // the source moved under it. The screen says so rather than
                // discarding it.
                'stale' => $stale,
                'missing' => $held === null,
            ];
        }

        return [
            'locale' => $locale,
            'name' => $this->locales->name($locale),
            'native' => $this->locales->native($locale),
            'dir' => $this->locales->dir($locale),
            'flag' => $this->locales->flag($locale),
            'status' => $this->translator->status($event, $locale),
            'error' => $entry['error'],
            'provider' => $entry['provider'],
            'model' => $entry['model'],
            'human' => $humanCount,
            'stale' => $staleCount,
            'total' => count($document),
            'translated' => $translated,
            'updated_at' => $entry['updated_at'],
            'fields' => $values,
        ];
    }

    /**
     * A field key as a person would name it. Deliberately a small map rather
     * than a lang file lookup per key: these are the labels of ONE record type
     * and they are read by the organiser, in the interface's language.
     */
    private function label(string $field): string
    {
        [$head, $rest] = array_pad(explode('.', $field, 2), 2, null);

        $base = match ($head) {
            'title' => __('translation::messages.field_title'),
            'about' => __('translation::messages.field_about'),
            'location' => __('translation::messages.field_location'),
            'level' => __('translation::messages.field_level'),
            'prize' => __('translation::messages.field_prize'),
            'cta_text' => __('translation::messages.field_cta'),
            'ribbon_label' => __('translation::messages.field_ribbon'),
            'requirements' => __('translation::messages.field_requirement'),
            'divisions' => __('translation::messages.field_division'),
            'fees' => __('translation::messages.field_fee'),
            default => $head,
        };

        // "Requirement 3" reads better than "requirements.2" — and the number
        // is 1-based because it is for a person, not an array.
        if ($rest !== null && is_numeric($rest)) {
            return $base.' '.((int) $rest + 1);
        }

        return $base;
    }

    /**
     * The event, or nothing at all.
     *
     * Bound by uuid and authorised with the event console's own predicate. A
     * 404 for an event that exists but is not this person's is deliberate:
     * `canManage` failing must not be distinguishable from "no such event".
     */
    private function event(string $uuid): ClubEvent
    {
        $event = ClubEvent::query()->where('uuid', $uuid)->first();

        abort_if($event === null, 404);

        $user = Auth::user();

        abort_unless($user !== null && $this->access->canManage($event, $user), 404);

        return $event;
    }
}

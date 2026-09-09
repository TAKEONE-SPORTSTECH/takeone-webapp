<?php

namespace Tests\Feature\Translation;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventFeeOption;
use App\Translation\Jobs\TranslateContent;
use App\Translation\Models\TranslationDocument;
use App\Translation\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The translation engine.
 *
 * The tests that matter here are not "does it translate" — that is the
 * provider's job — but the four promises the module makes around it:
 *
 *   1. a page with no translations renders exactly as it always did;
 *   2. an organiser's correction is never overwritten by a machine;
 *   3. editing one field re-translates that field and nothing else;
 *   4. a translation that would change a number is refused, not shown.
 *
 * No test here touches the network: the AI provider is faked at the HTTP layer,
 * so the whole agent — prompt, parse, guards, storage — is exercised for real
 * against a canned reply.
 */
class ContentTranslationTest extends TestCase
{
    private function makeEvent(array $attrs = []): ClubEvent
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy']);

        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Spring Open',
            'description' => 'Come and compete.',
            'location' => 'Isa Sports City',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'status' => 'active',
            'is_archived' => false,
            'source_locale' => 'en',
        ], $attrs));
    }

    /**
     * One stored field, straight out of the JSON document.
     *
     * Since 2026-09-09 every language of a record lives in ONE row, so the
     * tests read it the way the app does rather than querying per-field rows.
     *
     * @return array{v: string, h: string, o: string}|null
     */
    private function stored(ClubEvent $event, string $locale, string $field): ?array
    {
        $doc = TranslationDocument::forRecord('club_event', $event->id);

        return $doc[$locale]['fields'][$field] ?? null;
    }

    /** One language's status / provenance entry. */
    private function entry(ClubEvent $event, string $locale): array
    {
        return TranslationDocument::entry(TranslationDocument::forRecord('club_event', $event->id), $locale);
    }

    /** Fake the provider with a canned JSON reply keyed by field. */
    private function fakeProvider(array $values): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => json_encode($values, JSON_UNESCAPED_UNICODE)],
        ], 200)]);
    }

    /**
     * Run the real job, synchronously.
     *
     * `dispatchSync` rather than newing it up and handing it its collaborators:
     * the container resolves those, so this test names none of the module's
     * internals — which is the same boundary ModuleBoundaryTest enforces on
     * application code, and it exercises the actual dispatch path besides.
     */
    private function runJob(ClubEvent $event, string $locale): void
    {
        TranslateContent::dispatchSync($event->getMorphClass(), $event->id, $locale);
    }

    // ── 1. Nothing translated: the page is exactly what it was ──────────────

    public function test_an_event_with_no_translations_reads_as_its_source(): void
    {
        $event = $this->makeEvent();

        $doc = Translations::of($event, 'pt');

        $this->assertFalse($doc->any());
        $this->assertSame('Spring Open', $doc->get('title', $event->title));
        $this->assertSame('missing', Translations::status($event, 'pt'));
    }

    public function test_an_event_entirely_in_one_language_needs_no_translation_into_it(): void
    {
        // Arabic text on an Arabic-source event: genuinely nothing to do.
        $event = $this->makeEvent([
            'source_locale' => 'ar',
            'title' => 'بطولة الربيع',
            'description' => 'تعال وشارك في البطولة.',
            'location' => 'مدينة عيسى الرياضية',
        ]);

        $this->assertSame('source', Translations::status($event, 'ar'));
        $this->assertSame('source', Translations::ensure($event, 'ar'));
        $this->assertTrue(Translations::of($event, 'ar')->isSourceLanguage);
    }

    public function test_a_field_written_in_another_language_is_translated_even_into_the_source_language(): void
    {
        /*
         * The bug this replaced a test for. An event declared English whose
         * description is Arabic used to short-circuit on "English is the source
         * language, nothing to do" and serve the Arabic paragraph raw to an
         * English reader. Detection is per FIELD now.
         */
        $event = $this->makeEvent([
            'source_locale' => 'en',
            'title' => 'Spring Open',                        // English
            'description' => 'تعال وشارك في البطولة.',        // Arabic
            'location' => 'Isa Sports City',                 // English
        ]);

        $pending = Translations::pending($event, 'en');

        $this->assertSame(['about'], array_keys($pending), 'only the Arabic field needs translating into English');
        $this->assertNotSame('source', Translations::status($event, 'en'));
    }

    public function test_a_field_already_in_the_readers_language_is_never_sent(): void
    {
        $event = $this->makeEvent([
            'source_locale' => 'en',
            'title' => 'Spring Open',
            'description' => 'تعال وشارك في البطولة.',
            'location' => 'Isa Sports City',
        ]);

        // The Arabic reader needs the English fields — and must NOT be charged
        // for re-writing the organiser's own Arabic paragraph.
        $pending = Translations::pending($event, 'ar');

        $this->assertContains('title', array_keys($pending));
        $this->assertContains('location', array_keys($pending));
        $this->assertNotContains('about', array_keys($pending), "the organiser's own Arabic is left alone");
    }

    public function test_a_language_the_platform_does_not_serve_is_refused(): void
    {
        $event = $this->makeEvent();

        $this->assertSame('unavailable', Translations::ensure($event, '../../etc/passwd'));
        $this->assertSame('unavailable', Translations::status($event, 'klingon'));
        $this->assertNull(Translations::locales()->normalise('zz'));

        // A regional tag falls back to the base language we do serve.
        $this->assertSame('pt', Translations::locales()->normalise('pt-BR'));
        $this->assertSame('zh-TW', Translations::locales()->normalise('zh-TW'));
    }

    // ── 2. The document is the words, and only the words ────────────────────

    public function test_the_document_is_words_not_columns(): void
    {
        $event = $this->makeEvent(['level' => 'All', 'prize' => 'Medals', 'color' => '#ff0000']);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Heading', 'sort_order' => 2, 'is_heading' => true]);
        $event->update(['requirements' => ['Bring a gi', '']]);

        $doc = Translations::document($event->fresh());

        $this->assertArrayHasKey('title', $doc);
        $this->assertArrayHasKey('about', $doc);
        $this->assertArrayHasKey('prize', $doc);
        $this->assertArrayHasKey('requirements.0', $doc);

        // Empty entries, headings, and values with no letter in them are not words.
        $this->assertArrayNotHasKey('requirements.1', $doc);
        $this->assertArrayNotHasKey('color', $doc);
        $this->assertSame(
            ['Senior Men -58 kg'],
            array_values(array_filter($doc, fn ($k) => str_starts_with($k, 'divisions.'), ARRAY_FILTER_USE_KEY)),
        );
    }

    public function test_divisions_and_fee_lines_are_keyed_by_id_so_reordering_cannot_swap_them(): void
    {
        $event = $this->makeEvent();
        $a = EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Black', 'sort_order' => 1]);
        $b = EventCategory::create(['event_id' => $event->id, 'name' => 'Juvenile Blue', 'sort_order' => 2]);

        $doc = Translations::document($event->fresh());
        $this->assertSame('Adult Black', $doc['divisions.'.$a->id]);
        $this->assertSame('Juvenile Blue', $doc['divisions.'.$b->id]);

        // Swap their order; each keeps its own key.
        $a->update(['sort_order' => 2]);
        $b->update(['sort_order' => 1]);

        $doc = Translations::document($event->fresh());
        $this->assertSame('Adult Black', $doc['divisions.'.$a->id]);
        $this->assertSame('Juvenile Blue', $doc['divisions.'.$b->id]);
    }

    // ── 3. A full run, through the real agent, against a faked provider ─────

    public function test_a_run_stores_the_translation_and_the_page_reads_it(): void
    {
        $event = $this->makeEvent();
        $fee = EventFeeOption::create([
            'event_id' => $event->id, 'role' => 'participant',
            'label' => 'Registration fee', 'amount' => 10, 'is_active' => true, 'sort' => 0,
        ]);

        $this->fakeProvider([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
            'fees.'.$fee->id => 'Taxa de inscrição',
        ]);

        $this->runJob($event, 'pt');

        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));

        $doc = Translations::of($event->fresh(), 'pt');
        $this->assertSame('Abertura da Primavera', $doc->get('title'));
        $this->assertSame('Taxa de inscrição', $doc->get('fees.'.$fee->id));

        $this->assertSame('machine', $this->stored($event, 'pt', 'title')['o']);
    }

    public function test_a_field_the_provider_invents_is_dropped(): void
    {
        $event = $this->makeEvent();

        $this->fakeProvider([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
            // Not a field that was asked about — a hallucination, or worse.
            'password' => 'hunter2',
            'admin' => 'true',
        ]);

        $this->runJob($event, 'pt');

        $this->assertNull($this->stored($event, 'pt', 'password'));
        $this->assertNull($this->stored($event, 'pt', 'admin'));
    }

    // ── 4. The guard that caught a real bug ─────────────────────────────────

    public function test_a_translation_that_changes_a_number_is_refused(): void
    {
        $event = $this->makeEvent();
        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => '-Group ( A) No-Gi 60', 'sort_order' => 1,
        ]);
        $kept = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Group C (70+)', 'sort_order' => 2,
        ]);

        $this->fakeProvider([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
            // The real failure: under-60 came back with no bound at all.
            'divisions.'.$division->id => 'Grupo A (No-Gi) 60',
            'divisions.'.$kept->id => 'Grupo C (70+)',
        ]);

        $this->runJob($event->fresh(), 'pt');

        // Refused: a competitor must never be shown the wrong division.
        $this->assertNull($this->stored($event, 'pt', 'divisions.'.$division->id));
        // The honest one is kept.
        $this->assertSame('Grupo C (70+)', $this->stored($event, 'pt', 'divisions.'.$kept->id)['v']);

        // And the page shows that division in the source language, not wrongly.
        $doc = Translations::of($event->fresh(), 'pt');
        $this->assertSame('-Group ( A) No-Gi 60', $doc->get('divisions.'.$division->id, '-Group ( A) No-Gi 60'));
    }

    public function test_a_refused_field_does_not_keep_the_language_unfinished_forever(): void
    {
        $event = $this->makeEvent();
        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A (60-)', 'sort_order' => 1]);

        $this->fakeProvider([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
            'divisions.'.$division->id => 'Grupo A (60)',   // loses the bound → refused
        ]);

        $this->runJob($event->fresh(), 'pt');

        // Readable — title and description are there — so the language is done
        // and a visitor's request does not re-queue the same doomed job.
        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));

        Queue::fake();
        $this->assertSame('ready', Translations::ensure($event->fresh(), 'pt'));
        Queue::assertNothingPushed();
    }

    // ── 5. The invariant the whole review feature rests on ──────────────────

    public function test_a_human_translation_is_never_overwritten_by_a_machine(): void
    {
        $event = $this->makeEvent();

        $this->fakeProvider([
            'title' => 'Machine Title', 'about' => 'Machine about.', 'location' => 'Isa Sports City',
        ]);
        $this->runJob($event, 'pt');

        // A person corrects it.
        Translations::correct($event, 'pt', 'title', 'Título Escolhido à Mão');

        // The machine runs again, over the same field.
        $this->fakeProvider([
            'title' => 'A DIFFERENT Machine Title', 'about' => 'Machine about.', 'location' => 'Isa Sports City',
        ]);
        $this->runJob($event->fresh(), 'pt');

        $this->assertSame('Título Escolhido à Mão', $this->stored($event, 'pt', 'title')['v']);
        $this->assertSame('human', $this->stored($event, 'pt', 'title')['o']);
    }

    public function test_a_human_translation_survives_an_edit_to_the_source(): void
    {
        $event = $this->makeEvent();

        Translations::correct($event, 'pt', 'title', 'Escolhido à Mão');

        $event->update(['title' => 'Spring Open 2027']);
        Translations::forget($event, 'pt');

        // Still shown — a person's words do not become wrong because the
        // English moved — and never queued for the machine to redo.
        $this->assertSame('Escolhido à Mão', Translations::of($event->fresh(), 'pt')->get('title'));
        $this->assertArrayNotHasKey('title', Translations::pending($event->fresh(), 'pt'));
    }

    // ── 6. The cost model: one edited field, one field retranslated ─────────

    public function test_editing_one_field_makes_only_that_field_stale(): void
    {
        $event = $this->makeEvent(['prize' => 'Medals']);

        $this->fakeProvider([
            'title' => 'Abertura', 'about' => 'Venha competir.',
            'location' => 'Isa Sports City', 'prize' => 'Medalhas',
        ]);
        $this->runJob($event, 'pt');

        $this->assertSame([], Translations::pending($event->fresh(), 'pt'));
        $this->assertFalse(Translations::hasStale($event->fresh(), 'pt'));

        $event->update(['prize' => 'Medals and ranking points']);
        Translations::forget($event, 'pt');

        $this->assertSame(['prize'], array_keys(Translations::pending($event->fresh(), 'pt')));
        $this->assertTrue(Translations::hasStale($event->fresh(), 'pt'));

        // The stale machine row is not shown; the source is.
        $doc = Translations::of($event->fresh(), 'pt');
        $this->assertNull($doc->get('prize'));
        $this->assertSame('Abertura', $doc->get('title'));
    }

    // ── 7. Dispatch discipline ──────────────────────────────────────────────

    public function test_ensure_queues_once_and_a_second_caller_joins_the_first(): void
    {
        Queue::fake();
        $event = $this->makeEvent();

        $this->assertSame('preparing', Translations::ensure($event, 'pt'));
        $this->assertSame('preparing', Translations::ensure($event, 'pt'));
        $this->assertSame('preparing', Translations::ensure($event, 'pt'));

        Queue::assertPushed(TranslateContent::class, 1);
        $this->assertSame('queued', $this->entry($event, 'pt')['status']);
    }

    public function test_a_recent_failure_is_not_retried_on_every_request(): void
    {
        Queue::fake();
        $event = $this->makeEvent();

        TranslationDocument::mutate('club_event', $event->id, fn ($d) => TranslationDocument::setStatus($d, 'pt', 'failed', 'nope'));

        $this->assertSame('failed', Translations::ensure($event, 'pt'));
        Queue::assertNothingPushed();
    }

    public function test_a_worker_killed_mid_job_does_not_cost_that_language_forever(): void
    {
        Queue::fake();
        $event = $this->makeEvent();

        TranslationDocument::mutate('club_event', $event->id, function ($d) {
            $d = TranslationDocument::setStatus($d, 'pt', 'running');
            // Stamped an hour ago: a worker that died mid-job.
            $d['pt']['updated_at'] = now()->subHour()->toIso8601String();

            return $d;
        });

        $this->assertSame('preparing', Translations::ensure($event->fresh(), 'pt'));
        Queue::assertPushed(TranslateContent::class, 1);
    }

    public function test_a_provider_failure_is_recorded_without_leaking_its_body(): void
    {
        $event = $this->makeEvent();
        Http::fake(['*' => Http::response('the prompt was: Spring Open ... sk-secret', 500)]);

        $this->runJob($event, 'pt');

        $entry = $this->entry($event, 'pt');
        $this->assertSame('failed', $entry['status']);
        $this->assertStringNotContainsString('sk-secret', (string) $entry['error']);
        $this->assertStringNotContainsString('Spring Open', (string) $entry['error']);
    }

    // ── 8. The page itself ──────────────────────────────────────────────────

    public function test_the_public_payload_renders_in_the_readers_language(): void
    {
        $event = $this->makeEvent(['entry_mode' => 'public']);
        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Black', 'sort_order' => 1]);

        $this->fakeProvider([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
            'divisions.'.$division->id => 'Adulto Faixa Preta',
        ]);
        $this->runJob($event, 'pt');

        app()->setLocale('pt');
        Translations::forget($event, 'pt');

        $payload = app(\App\Events\Support\PublicEvent::class)->payload($event->fresh());

        $this->assertSame('Abertura da Primavera', $payload['title']);
        $this->assertSame('Venha competir.', $payload['about']);
        $this->assertSame(['Adulto Faixa Preta'], $payload['divisions']);

        // …and in English it is untouched.
        app()->setLocale('en');
        Translations::forget($event, 'en');
        $payload = app(\App\Events\Support\PublicEvent::class)->payload($event->fresh());
        $this->assertSame('Spring Open', $payload['title']);
    }

    public function test_a_new_event_records_the_language_it_was_written_in(): void
    {
        app()->setLocale('ar');
        $event = $this->makeEvent(['source_locale' => null]);

        $this->assertSame('ar', $event->fresh()->source_locale);
        $this->assertSame('ar', $event->fresh()->sourceLocale());
    }

    // ── 9. Not tied to any one AI company ───────────────────────────────────

    /** A configured provider row, so the chain has something to pick up. */
    private function provider(string $driver, array $attrs = []): \App\Models\AiProvider
    {
        return \App\Models\AiProvider::create(array_merge([
            'name' => ucfirst($driver),
            'modality' => 'text',
            'driver' => $driver,
            'api_key' => 'test-key',
            'model' => $driver.'-model',
            'enabled' => true,
            'is_default' => false,
        ], $attrs));
    }

    public function test_a_dead_provider_falls_through_to_the_next_one(): void
    {
        $event = $this->makeEvent();
        $this->provider('anthropic', ['is_default' => true]);

        Http::fake([
            // The paid one is having an outage.
            'api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529),
            // The local one picks it up.
            '*' => Http::response(['message' => ['content' => json_encode([
                'title' => 'Abertura da Primavera',
                'about' => 'Venha competir.',
                'location' => 'Isa Sports City',
            ])]], 200),
        ]);

        $this->runJob($event, 'pt');

        // The reader gets their language; nobody learns the API was down.
        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));
        $this->assertSame('Abertura da Primavera', Translations::of($event->fresh(), 'pt')->get('title'));

        // And the row records WHO actually wrote it, not who was asked first.
        $this->assertSame('ollama', $this->entry($event, 'pt')['provider']);
    }

    public function test_a_provider_that_answers_with_nothing_usable_is_passed_over(): void
    {
        $event = $this->makeEvent();
        $this->provider('anthropic', ['is_default' => true]);

        Http::fake([
            // 200, but prose instead of the JSON it was asked for.
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'I would be happy to help you translate this!']],
            ], 200),
            '*' => Http::response(['message' => ['content' => json_encode([
                'title' => 'Abertura da Primavera',
                'about' => 'Venha competir.',
                'location' => 'Isa Sports City',
            ])]], 200),
        ]);

        $this->runJob($event, 'pt');

        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));
        $this->assertSame('ollama', $this->entry($event, 'pt')['provider']);
    }

    public function test_the_configured_order_is_the_order_tried(): void
    {
        $event = $this->makeEvent();

        // Local first, paid second — the "cheap by default, paid safety net"
        // arrangement config/translation.php exists to make possible.
        $local = $this->provider('ollama', ['name' => 'My server', 'base_url' => 'http://127.0.0.1:11434']);
        $paid = $this->provider('anthropic', ['is_default' => true]);

        config(['translation.providers' => [$local->id, $paid->id]]);

        Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
        ])]], 200)]);

        $this->runJob($event, 'pt');

        // The local one answered, so nothing was ever asked of the paid one.
        $this->assertSame('ollama', $this->entry($event, 'pt')['provider']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic'));
    }

    public function test_with_nothing_configured_at_all_the_built_in_local_model_answers(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(0, \App\Models\AiProvider::count());

        Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
            'title' => 'Abertura da Primavera',
            'about' => 'Venha competir.',
            'location' => 'Isa Sports City',
        ])]], 200)]);

        $this->runJob($event, 'pt');

        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));
    }

    public function test_when_every_model_fails_the_page_still_reads_in_its_own_language(): void
    {
        $event = $this->makeEvent();
        $this->provider('anthropic', ['is_default' => true]);

        Http::fake(['*' => Http::response('down', 500)]);

        $this->runJob($event, 'pt');

        $this->assertSame('failed', $this->entry($event, 'pt')['status']);

        // The whole point: the poster is still a poster.
        $this->assertSame('Spring Open', Translations::of($event->fresh(), 'pt')->get('title', $event->title));
    }

    public function test_a_self_hosted_model_is_asked_for_json_at_the_api_level(): void
    {
        $event = $this->makeEvent();
        $this->provider('ollama', ['base_url' => 'http://127.0.0.1:11434', 'is_default' => true]);

        Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
            'title' => 'Abertura', 'about' => 'Venha competir.', 'location' => 'Isa Sports City',
        ])]], 200)]);

        $this->runJob($event, 'pt');

        // `format: json` is what makes a 7B model reliable at structured
        // output — without it the reply arrives wrapped in a sentence.
        Http::assertSent(fn ($request) => ($request->data()['format'] ?? null) === 'json');
    }

    public function test_a_broken_provider_row_does_not_take_translation_down(): void
    {
        $event = $this->makeEvent();

        // A driver name nothing can build — a half-filled admin form.
        $this->provider('some-driver-that-does-not-exist', ['is_default' => true]);

        Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
            'title' => 'Abertura', 'about' => 'Venha competir.', 'location' => 'Isa Sports City',
        ])]], 200)]);

        $this->runJob($event, 'pt');

        $this->assertSame('ready', Translations::status($event->fresh(), 'pt'));
    }
}

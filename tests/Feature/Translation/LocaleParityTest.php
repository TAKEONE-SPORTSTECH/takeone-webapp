<?php

namespace Tests\Feature\Translation;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventChecklistItem;
use App\Models\EventFeeOption;
use App\Support\Cldr;
use App\Translation\Models\TranslationDocument;
use App\Translation\Translations;
use Tests\TestCase;

/**
 * The acceptance bar, as a test: pick a locale, walk a screen, and there is not
 * one English word that is not a proper noun.
 *
 * ── Why this exists rather than a list of fixed strings ──────────────────────
 *
 * Every previous attempt at this problem was a list of strings somebody had
 * noticed. The list was fixed, the page stayed half English, and the next
 * report named different strings. A test that names strings can only ever be as
 * complete as the person who wrote it.
 *
 * So this one names none. It renders the page, takes `lang/en` as the corpus of
 * everything the interface can say, and asserts that none of it came out in
 * English — which catches the string nobody has looked at yet, and keeps
 * catching it when somebody adds a two hundredth key next month.
 *
 * ⚠️ It measures TWO systems at once, on purpose, because a reader cannot tell
 * them apart: the chrome (`lang/<code>/`) and the organiser's own words
 * (App\Translation). A page whose buttons are Japanese and whose fee lines are
 * Arabic has failed, whichever half is at fault.
 *
 * ── What it deliberately tolerates ───────────────────────────────────────────
 *
 * `ParityAudit::ALLOWLIST` — the platform's name, `Gi`, `BHD`, a handful of
 * sport words a rule book fixes. It is short, and every entry is a claim that a
 * word is the same in sixty-eight languages.
 */
class LocaleParityTest extends TestCase
{
    /** The languages this test holds the platform to. */
    private const LOCALES = ['ar', 'zh', 'ja', 'fr', 'hi'];

    private function club(User $owner): Tenant
    {
        return $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy']);
    }

    /**
     * A public event with everything a poster shows: divisions, priced entry
     * lines, a readiness list and a paragraph of prose.
     */
    private function publicEvent(): ClubEvent
    {
        $owner = $this->createUser();
        $club = $this->club($owner);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Spring Open',
            'description' => 'Come and compete. Bring your own gi.',
            'location' => 'Isa Sports City',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'public',
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'enrollment_ends_at' => now()->addWeek(),
            'status' => 'active',
            'is_archived' => false,
            'source_locale' => 'en',
            'entry_mode' => 'public',
            'participant_fee_amount' => 20,
            'fee_currency' => 'BHD',
        ]);

        EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Lightweight', 'weight_class' => '-45kg', 'sort_order' => 1]);
        EventFeeOption::create(['event_id' => $event->id, 'role' => 'participant', 'label' => 'Team entry', 'amount' => 5, 'is_active' => true, 'sort' => 1]);
        EventChecklistItem::create(['event_id' => $event->id, 'label' => 'Scales calibrated', 'sort_order' => 1]);

        return $event;
    }

    /**
     * Translate everything the record publishes, the way a finished run leaves
     * it — so a failure here is the INTERFACE's, never a missing content run.
     */
    private function translateContent(ClubEvent $event, string $locale): void
    {
        $document = Translations::document($event);
        $values = [];
        $hashes = [];

        foreach ($document as $field => $source) {
            // A marker rather than real prose: it must not collide with any
            // English string, or the audit would read the translation itself
            // as a fallback.
            $values[$field] = '«'.$locale.'·'.$field.'»';
            $hashes[$field] = Translations::hash($source);
        }

        TranslationDocument::mutate('club_event', $event->id, function ($doc) use ($locale, $values, $hashes) {
            [$doc] = TranslationDocument::mergeMachine($doc, $locale, $values, $hashes, 'test', 'test-model');

            return TranslationDocument::setStatus($doc, $locale, 'ready');
        });

        Translations::forget($event, $locale);
    }

    /**
     * The proper nouns on this page — what a translator is RIGHT to leave in
     * the source language.
     *
     * The same `keep` list the translation agent itself is given, so the audit
     * and the translator cannot disagree about what counts as a name. Without
     * it, a club called "Victory Academy" makes every page it hosts look like
     * it leaked the English word "Victory".
     *
     * @return array<int, string>
     */
    private function namesOn(ClubEvent $event): array
    {
        return Translations::source(function () use ($event) {
            $context = $event->translationContext();

            return array_filter(array_merge(
                (array) ($context['keep'] ?? []),
                [$event->title, $event->tenant?->club_name, $event->location],
            ));
        });
    }

    /**
     * ⚠️ THE TEST THIS WHOLE EXERCISE EXISTS FOR.
     *
     * Failed on 2026-09-09 before the work with 144 English strings on the
     * Chinese poster and 0% of the interface resolved. It is expected to keep
     * failing for a locale whose lang files have not been generated yet — that
     * is the point: the failure message is the to-do list, and it names the
     * keys.
     */
    public function test_a_public_event_page_carries_no_english_in_any_locale(): void
    {
        $event = $this->publicEvent();
        $summary = [];

        foreach (self::LOCALES as $locale) {
            $this->translateContent($event, $locale);

            $html = $this->withHeaders(['Accept-Language' => $locale])
                ->get("/e/{$event->uuid}")
                ->assertOk()
                ->getContent();

            $report = Translations::parity($html, $locale, $this->namesOn($event));

            $summary[$locale] = sprintf(
                '%s: %d rendered, %d resolved, %d fell back%s',
                $locale,
                $report['rendered'],
                $report['resolved'],
                $report['fell_back'],
                $report['fell_back'] === 0
                    ? ''
                    : ' — e.g. '.implode(' | ', array_slice(array_keys($report['leaked']), 0, 6)),
            );
        }

        $failed = array_filter($summary, fn ($line) => str_contains($line, 'fell back — e.g.'));

        $this->assertSame(
            [],
            $failed,
            "English leaked into a non-English page:\n  ".implode("\n  ", $summary),
        );
    }

    /**
     * The entry form and the readiness list were the two surfaces that read the
     * raw column while the poster two taps away read the translation.
     *
     * Named explicitly, and separately from the sweep above, because they are
     * the ones that proved a whole-page audit was needed: both were fed by the
     * same stored translation and neither used it.
     */
    public function test_the_entry_form_and_the_checklist_never_show_the_source_language(): void
    {
        $event = $this->publicEvent();
        $this->translateContent($event, 'ja');

        $html = $this->withHeaders(['Accept-Language' => 'ja'])
            ->get("/e/{$event->uuid}/enter")
            ->assertOk()
            ->getContent();

        // The fee line the organiser typed in English must not be on a Japanese
        // entry form; the stored Japanese must be.
        $this->assertStringNotContainsString('Team entry', $html);
        $this->assertStringContainsString('«ja·fees.', $html);
    }

    /**
     * The organiser's own words survive the reader's language.
     *
     * The other half of the bargain, and the dangerous one: an edit form that
     * showed the machine's Japanese would save it over the English source, with
     * no way back. Pinned by App\Http\Middleware\ReadsSourceContent.
     */
    public function test_an_edit_form_shows_the_organiser_their_own_words(): void
    {
        $event = $this->publicEvent();
        $this->translateContent($event, 'ja');

        $html = $this->actingAs(User::findOrFail($event->created_by))
            ->withHeaders(['Accept-Language' => 'ja'])
            ->get("/me/events/{$event->uuid}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Spring Open', $html);
        $this->assertStringNotContainsString('«ja·title»', $html);
    }

    /**
     * Dates, times and money in the locale's OWN pattern — not English word
     * order with the words swapped.
     *
     * The regression guarded here is `周五 18 9月` and `3:00 下午`: Chinese
     * tokens forced into `D j M` and `g:i A`. Chinese writes the date
     * month-first and keeps a 24-hour clock, and no single pattern string can
     * be right for both it and English.
     */
    public function test_dates_and_times_use_each_locales_own_pattern(): void
    {
        if (! Cldr::available()) {
            $this->markTestSkipped('ext-intl is not installed; Cldr falls back to Carbon.');
        }

        $when = \Illuminate\Support\Carbon::parse('2026-09-18 15:00:00', config('app.timezone'));

        $expected = [
            // locale => [short date, time]
            'en' => ['Sep 18', '3:00 PM'],
            'zh' => ['9月18日', '15:00'],
            'ja' => ['9月18日', '15:00'],
            'fr' => ['18 sept.', '15:00'],
        ];

        foreach ($expected as $locale => [$date, $time]) {
            app()->setLocale($locale);

            // ICU separates the time from its meridiem with a NARROW NO-BREAK
            // SPACE (U+202F). That is correct typography and invisible in a
            // diff, so normalise before comparing rather than pinning the test
            // to one ICU version's choice of space.
            $this->assertSame($date, $this->plainSpaces(Cldr::shortDate($when)), "short date in {$locale}");
            $this->assertSame($time, $this->plainSpaces(Cldr::time($when)), "time in {$locale}");
        }

        // A number and an amount follow the locale too — grouping, decimal
        // mark and the side the currency sits on.
        app()->setLocale('fr');
        $this->assertStringContainsString('BHD', Cldr::money(5, 'BHD'));
        $this->assertSame('1 234', $this->plainSpaces(Cldr::number(1234)));
    }

    /** Every flavour of Unicode space flattened to a plain one. */
    private function plainSpaces(string $value): string
    {
        return trim((string) preg_replace('/[\x{00A0}\x{202F}\x{2009}\s]+/u', ' ', $value));
    }
}

<?php

namespace Tests\Feature\Translation;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Translation\Jobs\TranslateContent;
use App\Translation\Models\TranslationDocument;
use App\Translation\Translations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The two doors into the translation module, and who may open them.
 *
 * The public one is the interesting one: it is reachable by anybody with a
 * link, by necessity — a stranger deciding whether to enter a competition has
 * no account — and each admitted request can start work that costs money. So
 * these tests are mostly about what it REFUSES.
 */
class TranslationRoutesTest extends TestCase
{
    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
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
            'entry_mode' => 'public',
        ], $attrs));
    }

    /**
     * Put a machine translation into the document, the way a run would.
     *
     * @param  array<int,string>|null  $only  fields to translate; null = all
     */
    private function machineTranslate(ClubEvent $event, string $locale, ?array $only = null): void
    {
        $document = Translations::document($event);
        $values = [];
        $hashes = [];

        foreach ($document as $field => $source) {
            if ($only !== null && ! in_array($field, $only, true)) {
                continue;
            }

            $values[$field] = strtoupper($locale).' '.$source;
            $hashes[$field] = Translations::hash($source);
        }

        TranslationDocument::mutate('club_event', $event->id, function ($doc) use ($locale, $values, $hashes) {
            [$doc] = TranslationDocument::mergeMachine($doc, $locale, $values, $hashes, 'test', 'test-model');

            return TranslationDocument::setStatus($doc, $locale, 'ready');
        });
    }

    // ── The visitor's door ──────────────────────────────────────────────────

    public function test_a_stranger_may_ask_for_a_language_on_a_public_event(): void
    {
        Queue::fake();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        /*
         * `ja` rather than `pt`: `interface` says whether the BUTTONS speak
         * this language too, and Portuguese was promoted into
         * config/locales.php once its lang files were generated. Asserting
         * `false` against a locale that may be promoted tomorrow makes this
         * test a tripwire on an unrelated decision.
         */
        $this->postJson("/e/{$event->uuid}/language", ['locale' => 'ja'])
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'preparing', 'ready' => false, 'interface' => false]);

        Queue::assertPushed(TranslateContent::class, 1);
    }

    public function test_a_language_the_interface_also_speaks_says_so(): void
    {
        Queue::fake();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        // The picker tells a reader, before they choose, whether they are
        // getting a translated EVENT or a translated PRODUCT.
        $this->postJson("/e/{$event->uuid}/language", ['locale' => 'pt'])
            ->assertOk()
            ->assertJson(['interface' => true]);
    }

    public function test_an_event_with_no_public_page_has_no_public_translation_door(): void
    {
        Queue::fake();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser, ['entry_mode' => 'members']);

        $this->postJson("/e/{$event->uuid}/language", ['locale' => 'pt'])->assertNotFound();
        $this->getJson("/e/{$event->uuid}/language/pt")->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_an_unknown_event_is_the_same_404(): void
    {
        $this->postJson('/e/'.\Illuminate\Support\Str::uuid().'/language', ['locale' => 'pt'])
            ->assertNotFound();
    }

    public function test_an_unserved_language_starts_nothing(): void
    {
        Queue::fake();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->postJson("/e/{$event->uuid}/language", ['locale' => 'klingon'])
            ->assertOk()
            ->assertJson(['status' => 'unavailable', 'ready' => false]);

        $this->getJson("/e/{$event->uuid}/language/..%2F..%2Fetc")->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_status_reports_ready_once_the_words_exist(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->machineTranslate($event, 'pt');

        $this->getJson("/e/{$event->uuid}/language/pt")
            ->assertOk()
            ->assertJson(['status' => 'ready', 'ready' => true, 'locale' => 'pt', 'dir' => 'ltr']);
    }

    public function test_the_source_language_is_always_ready(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser, ['source_locale' => 'ar']);

        $this->getJson("/e/{$event->uuid}/language/ar")
            ->assertOk()
            ->assertJson(['status' => 'source', 'ready' => true, 'dir' => 'rtl', 'interface' => true]);
    }

    // ── The organiser's door ────────────────────────────────────────────────

    public function test_the_organiser_reads_every_language_and_the_source_beside_it(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Black', 'sort_order' => 1]);

        $this->machineTranslate($event, 'pt', ['title']);

        $response = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/translations")
            ->assertOk()
            ->assertJson(['success' => true, 'source_locale' => 'en']);

        $data = $response->json();
        $this->assertSame('pt', $data['languages'][0]['locale']);
        $this->assertContains('title', array_column($data['fields'], 'field'));
        // Every language on offer is listed, for the "add a language" picker.
        $this->assertGreaterThan(10, count($data['available']));
    }

    public function test_someone_who_does_not_run_the_event_cannot_see_or_touch_its_translations(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $stranger = $this->createUser();

        // 404, not 403 — "not yours" must be indistinguishable from "no such event".
        $this->actingAs($stranger)->getJson("/me/events/{$event->uuid}/translations")->assertNotFound();
        $this->actingAs($stranger)->putJson("/me/events/{$event->uuid}/translations", [
            'locale' => 'pt', 'field' => 'title', 'value' => 'HACK',
        ])->assertNotFound();
        $this->actingAs($stranger)->postJson("/me/events/{$event->uuid}/translations", ['locale' => 'pt'])->assertNotFound();
        $this->actingAs($stranger)->deleteJson("/me/events/{$event->uuid}/translations", ['locale' => 'pt'])->assertNotFound();

        $this->assertSame([], TranslationDocument::forRecord('club_event', $event->id));
    }

    public function test_a_correction_is_saved_as_human_and_survives_a_machine_run(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser)
            ->putJson("/me/events/{$event->uuid}/translations", [
                'locale' => 'pt', 'field' => 'title', 'value' => 'Escolhido à Mão',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'origin' => 'human']);

        $held = TranslationDocument::forRecord('club_event', $event->id)['pt']['fields']['title'];
        $this->assertSame('Escolhido à Mão', $held['v']);
        $this->assertSame('human', $held['o']);

        // pending() is what a machine run would rewrite; a human row is not in it.
        $this->assertArrayNotHasKey('title', \App\Translation\Translations::pending($event->fresh(), 'pt'));
    }

    public function test_a_field_the_event_does_not_have_cannot_be_written(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser)
            ->putJson("/me/events/{$event->uuid}/translations", [
                'locale' => 'pt', 'field' => 'is_admin', 'value' => 'yes',
            ])
            ->assertStatus(422);

        $this->actingAs($organiser)
            ->putJson("/me/events/{$event->uuid}/translations", [
                'locale' => 'not-a-language', 'field' => 'title', 'value' => 'x',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('content_translations', 0);
    }

    public function test_clearing_a_correction_hands_the_field_back_to_the_machine(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/translations", [
            'locale' => 'pt', 'field' => 'title', 'value' => 'Escolhido à Mão',
        ])->assertOk();

        $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/translations", [
            'locale' => 'pt', 'field' => 'title', 'value' => '',
        ])->assertOk();

        $this->assertSame([], TranslationDocument::forRecord('club_event', $event->id));
        $this->assertArrayHasKey('title', \App\Translation\Translations::pending($event->fresh(), 'pt'));
    }

    public function test_removing_a_language_takes_the_organisers_own_edits_with_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/translations", [
            'locale' => 'pt', 'field' => 'title', 'value' => 'Escolhido à Mão',
        ])->assertOk();

        $this->actingAs($organiser)
            ->deleteJson("/me/events/{$event->uuid}/translations", ['locale' => 'pt'])
            ->assertOk()
            ->assertJson(['success' => true, 'locale' => 'pt']);

        $this->assertSame([], TranslationDocument::forRecord('club_event', $event->id));
    }

    public function test_the_organiser_can_queue_a_language_immediately(): void
    {
        Queue::fake();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser)
            ->postJson("/me/events/{$event->uuid}/translations", ['locale' => 'ja'])
            ->assertOk()
            ->assertJson(['success' => true, 'locale' => 'ja']);

        Queue::assertPushed(TranslateContent::class, 1);
    }
}

<?php

namespace Tests\Feature\Translation;

use App\Members\Models\User;
use App\Translation\Models\InterfaceTranslation;
use App\Translation\Translations;
use Tests\TestCase;

/**
 * The product's own words, as rows.
 *
 * The move off `lang/<code>/*.php` was made for three reasons, and the first
 * two are what these tests hold down: a deploy was going to delete eleven
 * generated languages (only en and ar are tracked), and a UI cannot be allowed
 * to write executable PHP. The third — that nobody could correct a word — is
 * the screen, and the invariant that makes correcting one worth doing is that a
 * machine never overwrites a person.
 */
class InterfaceStringsTest extends TestCase
{
    private function superAdmin(): User
    {
        $user = $this->createUser();
        $this->makeSuperAdmin($user);

        return $user->fresh();
    }

    /**
     * ⚠️ The regression that made the overlay a no-op for most of the
     * interface: Laravel's Translator passes the namespace `'*'` for a root
     * lang file, not null, so a loader that took it literally found no rows and
     * silently served the file for every un-namespaced string.
     */
    public function test_a_stored_string_is_what_the_page_renders(): void
    {
        Translations::correctInterface('zh', 'events', 'bout_gallery_title', 'ROWS-WIN');

        $this->assertSame('ROWS-WIN', __('events.bout_gallery_title', [], 'zh'));
    }

    /** A namespaced file — `scoreboard::bjj_messages` — resolves the same way. */
    public function test_a_namespaced_file_is_overlaid_too(): void
    {
        Translations::correctInterface('zh', 'scoreboard::bjj_messages', 'round_final', 'NS-ROWS');

        $this->assertSame('NS-ROWS', __('scoreboard::bjj_messages.round_final', [], 'zh'));
    }

    /**
     * THE invariant. A correction outranks the machine for good — otherwise
     * nobody would make one, which is exactly what happened while the strings
     * were files.
     */
    public function test_a_machine_run_never_overwrites_a_persons_words(): void
    {
        Translations::correctInterface('zh', 'events', 'bout_gallery_title', 'BY HAND');

        $this->assertFalse(
            Translations::recordInterface('zh', 'events', 'bout_gallery_title', 'BY MACHINE'),
            'a machine write over a human row must be refused',
        );

        $this->assertSame('BY HAND', __('events.bout_gallery_title', [], 'zh'));
    }

    /**
     * An empty value drops the correction rather than storing a blank.
     *
     * ⚠️ And what it falls back TO is the lang file, not English. The rows are
     * an OVERLAY, never a replacement: `lang/ar` is hand-written and tracked in
     * git, and any generated file still on disk keeps working. So "clear my
     * edit" hands the string back to whatever was underneath, which is the
     * behaviour somebody clearing an edit actually wants — not a page that
     * reverts to English because their correction was the only thing there.
     */
    public function test_clearing_a_correction_hands_the_string_back(): void
    {
        $underneath = __('events.bout_gallery_title', [], 'zh');

        Translations::correctInterface('zh', 'events', 'bout_gallery_title', 'TEMPORARY');
        $this->assertSame('TEMPORARY', __('events.bout_gallery_title', [], 'zh'));

        Translations::correctInterface('zh', 'events', 'bout_gallery_title', null);

        $this->assertSame(
            0,
            InterfaceTranslation::where('locale', 'zh')->where('key', 'bout_gallery_title')->count(),
        );

        $this->assertSame($underneath, __('events.bout_gallery_title', [], 'zh'));
    }

    /** The screen is the super-admin's, and nobody else's. */
    public function test_an_ordinary_member_cannot_reach_the_screen(): void
    {
        $this->actingAs($this->createUser())
            ->get('/admin/languages')
            ->assertRedirect('/');

        $this->actingAs($this->createUser())
            ->getJson('/admin/languages/zh/strings')
            ->assertForbidden();
    }

    public function test_the_super_admin_sees_every_language_and_can_correct_one(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin/languages')->assertOk();

        $this->actingAs($admin)
            ->putJson('/admin/languages/zh', [
                'file' => 'events',
                'key' => 'bout_gallery_title',
                'value' => 'FROM THE SCREEN',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('FROM THE SCREEN', __('events.bout_gallery_title', [], 'zh'));
    }

    /** A locale the platform does not serve is not a place to write rows. */
    public function test_an_unserved_locale_is_refused(): void
    {
        $this->actingAs($this->superAdmin())
            ->putJson('/admin/languages/xx', ['file' => 'events', 'key' => 'a', 'value' => 'b'])
            ->assertNotFound();

        // English is the SOURCE and is not stored here either.
        $this->actingAs($this->superAdmin())
            ->putJson('/admin/languages/en', ['file' => 'events', 'key' => 'a', 'value' => 'b'])
            ->assertNotFound();
    }

    /**
     * The listing shows every string the tier asks for, with its English
     * beside it — including the ones nothing has translated yet, because "what
     * is still English?" is the question somebody opens this screen to answer.
     */
    public function test_the_listing_pairs_each_string_with_its_english(): void
    {
        $page = Translations::interfaceStrings('zh', 'event', null, 'all', 1);

        $this->assertGreaterThan(0, $page['total']);
        $this->assertNotEmpty($page['rows'][0]['source'] ?? null, 'every row carries the English it came from');
        $this->assertArrayHasKey('missing', $page['counts']);

        // A string with no translation anywhere is what `missing` selects, and
        // its value is null rather than an empty string, so the screen can tell
        // "not translated" from "translated to nothing".
        $missing = Translations::interfaceStrings('zh', 'event', null, 'missing', 1);

        foreach ($missing['rows'] as $row) {
            $this->assertNull($row['value'], $row['file'].'.'.$row['key'].' is listed as missing but has a value');
        }
    }
}

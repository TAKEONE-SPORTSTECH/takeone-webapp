<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Switching to Arabic must flip the document direction, not just the strings.
 * All the RTL styling (`[dir="rtl"]` rules in app.css, Tailwind `rtl:` variants)
 * hangs off the `<html dir>` attribute — if it never says `rtl`, none of it
 * applies and the page renders Arabic text in an LTR layout.
 */
class LocaleDirectionTest extends TestCase
{
    public function test_arabic_locale_renders_rtl_direction(): void
    {
        $user = $this->createUser(['locale' => 'ar']);

        $this->actingAs($user)->get('/me')->assertSee('dir="rtl"', false);
    }

    public function test_english_locale_renders_ltr_direction(): void
    {
        $user = $this->createUser(['locale' => 'en']);

        $this->actingAs($user)->get('/me')->assertSee('dir="ltr"', false);
    }

    public function test_localised_pages_are_never_served_from_browser_cache(): void
    {
        $user = $this->createUser(['locale' => 'ar']);

        // The real fix for "Back still shows English": `no-store` forbids both
        // the bfcache and the HTTP disk cache, so Back must re-request and the
        // page re-renders in the current locale.
        $this->actingAs($user)->get('/me')
            ->assertOk()
            ->assertHeaderMissing('X-Should-Not-Exist');

        $response = $this->actingAs($user)->get('/me');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_a_relogged_page_reflects_the_switched_locale(): void
    {
        $user = $this->createUser(['locale' => 'en']);

        $this->actingAs($user)->get('/me')->assertSee('dir="ltr"', false);

        $this->actingAs($user)->putJson(route('me.locale.update'), ['locale' => 'ar'])->assertOk();

        // Re-requesting the same URL (what Back now does) yields Arabic + RTL.
        $this->actingAs($user->fresh())->get('/me')->assertSee('dir="rtl"', false);
    }

    public function test_locale_update_returns_direction_for_the_client(): void
    {
        $user = $this->createUser(['locale' => 'en']);

        $this->actingAs($user)
            ->putJson(route('me.locale.update'), ['locale' => 'ar'])
            ->assertOk()
            ->assertJson(['success' => true, 'locale' => 'ar', 'dir' => 'rtl']);

        $this->assertSame('ar', $user->fresh()->locale);
    }
}

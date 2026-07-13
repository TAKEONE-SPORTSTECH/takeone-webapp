<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Exiting impersonation restores the admin server-side. The bug it *looked*
 * like — "Back still shows the member" — is the browser replaying a bfcached
 * page. These cover both halves: the session really flips, and impersonated
 * responses carry `no-store` so Back cannot replay them.
 */
class ImpersonationTest extends TestCase
{
    public function test_impersonated_responses_are_not_cacheable(): void
    {
        $admin  = $this->createUser();
        $member = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->post("/admin/impersonate/{$member->id}");

        $this->assertSame($member->id, auth()->id());

        // Symfony reorders the directives and appends `private`, so match the
        // directive that matters rather than the serialized header.
        $response = $this->get('/me');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_leaving_impersonation_restores_the_admin(): void
    {
        $admin  = $this->createUser();
        $member = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->post("/admin/impersonate/{$member->id}");
        $this->assertSame($member->id, auth()->id());

        $this->post('/impersonate/leave')->assertRedirect(route('admin.platform.index'));

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has('impersonate.original_id'));
    }

    public function test_every_authenticated_page_is_non_cacheable(): void
    {
        // Broadened beyond impersonation: every signed-in page is per-user and
        // per-locale, so none may be replayed from a browser cache on Back.
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/me');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_guest_pages_are_not_forced_no_store_by_the_guard(): void
    {
        // A public page has no per-user state; the guard must leave it alone.
        // (/login sets no-store itself for CSRF reasons, so use /explore's
        // redirect-to-login instead: a guest redirect, untouched by the guard.)
        $response = $this->get('/explore');

        $response->assertRedirect();
        $this->assertStringNotContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
    }
}

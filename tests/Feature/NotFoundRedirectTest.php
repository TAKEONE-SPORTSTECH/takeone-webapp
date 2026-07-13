<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Signed-in web navigation to a 404 (missing route OR a gated/absent bound
 * model) must bounce back with a flashed error toast, not dead-end on a 404
 * page. AJAX still gets a real 404; guests still get the default 404.
 */
class NotFoundRedirectTest extends TestCase
{
    public function test_authenticated_web_404_redirects_back_with_a_flash(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from('/me')
            ->get('/this-route-does-not-exist')
            ->assertRedirect('/me')
            ->assertSessionHas('error');
    }

    public function test_gated_full_profile_redirects_instead_of_404(): void
    {
        $me = $this->createUser();
        $stranger = $this->createUser();

        // member.show firstOrFail()s for a non-family, non-admin viewer.
        $this->actingAs($me)
            ->from('/me/people')
            ->get("/member/{$stranger->uuid}")
            ->assertRedirect('/me/people')
            ->assertSessionHas('error');
    }

    public function test_direct_hit_with_no_referer_falls_back_home(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get('/nope-nothing-here')
            ->assertRedirect('/');
    }

    public function test_ajax_404_still_returns_json_not_found(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->getJson('/this-route-does-not-exist')
            ->assertNotFound();
    }

    public function test_guest_404_is_left_alone(): void
    {
        $this->get('/this-route-does-not-exist')->assertNotFound();
    }
}

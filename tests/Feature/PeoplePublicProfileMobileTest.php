<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The mobile public profile (people/mobile/show) is the SAFE profile: any
 * signed-in member may view it, so it must never render health, billing,
 * documents, contacts or family data — those live only on member.show.
 */
class PeoplePublicProfileMobileTest extends TestCase
{
    private function mobileGet(string $uri)
    {
        return $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
                .'(KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
        ])->get($uri);
    }

    public function test_mobile_public_profile_renders_for_a_stranger(): void
    {
        $me = $this->createUser();
        $person = $this->createUser(['full_name' => 'Public Athlete']);

        $this->actingAs($me)->mobileGet("/people/{$person->uuid}")
            ->assertOk()
            ->assertSee('Public Athlete')
            // Redesigned hero + floating stat card, built from existing tokens.
            ->assertSee('m-hero', false)
            ->assertSee('m-card', false);
    }

    public function test_mobile_public_profile_keeps_its_actions(): void
    {
        $me = $this->createUser();
        $person = $this->createUser();

        $this->actingAs($me)->mobileGet("/people/{$person->uuid}")
            ->assertOk()
            // follow toggle, challenge
            ->assertSee('following = !was', false)
            ->assertSee('bi-lightning-charge-fill', false);
    }

    public function test_mobile_public_profile_leaks_no_private_data(): void
    {
        $me = $this->createUser();
        $person = $this->createUser();

        $this->actingAs($me)->mobileGet("/people/{$person->uuid}")
            ->assertOk()
            ->assertDontSee('Billing')
            ->assertDontSee('Health Records')
            ->assertDontSee('Emergency')
            ->assertDontSee($person->email);
    }

    public function test_full_member_profile_is_denied_to_a_stranger(): void
    {
        $me = $this->createUser();
        $person = $this->createUser();

        // The boundary this page exists to protect. A browser GET no longer
        // dead-ends on a 404 (see NotFoundRedirectTest) — it bounces back with a
        // toast — but access is still denied: the full profile never renders.
        $this->actingAs($me)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) Mobile Safari/537.36',
                'Referer' => url('/me/people'),
            ])
            ->get("/member/{$person->uuid}")
            ->assertRedirect('/me/people')
            ->assertSessionHas('error');

        // And the API surface still 404s outright.
        $this->actingAs($me)->getJson("/member/{$person->uuid}")->assertNotFound();
    }
}

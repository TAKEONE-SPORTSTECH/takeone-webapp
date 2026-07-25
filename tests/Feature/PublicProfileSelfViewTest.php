<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicProfileSelfViewTest extends TestCase
{
    public function test_viewing_your_own_public_profile_redirects_to_the_full_profile(): void
    {
        $me = $this->createUser();
        $this->actingAs($me)
            ->get("/people/{$me->uuid}")
            ->assertRedirect(route('member.show', $me->uuid));
    }

    public function test_public_flag_shows_the_minimal_public_profile_even_for_self(): void
    {
        $me = $this->createUser();
        // ?public=1 must NOT redirect — it renders the safe public profile of yourself.
        $this->actingAs($me)
            ->get("/people/{$me->uuid}?public=1")
            ->assertOk();
    }
}

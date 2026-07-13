<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScheduleAvatarsRemovedTest extends TestCase
{
    private function mobileGet(string $uri)
    {
        return $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
                .'(KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
        ])->get($uri);
    }

    public function test_schedule_renders_without_the_family_avatar_row(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/schedule')
            ->assertOk()
            ->assertDontSee('sched-avatars', false)
            ->assertDontSee('renderAvatars', false);
    }

    public function test_schedule_still_renders_its_other_sections(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->mobileGet('/me/schedule')
            ->assertOk()
            ->assertSee('sched-strip', false)
            // avatarHTML is still used by the session cards.
            ->assertSee('avatarHTML', false);
    }
}

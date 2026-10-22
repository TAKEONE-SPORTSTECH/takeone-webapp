<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Contracts\ContractTestCase;

/**
 * The event console is always light (owner, 2026-10-16), whatever the
 * device's theme switch says — every other page still follows it.
 */
class ConsoleAlwaysLightTest extends ContractTestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    public function test_the_console_ignores_a_dark_theme_and_other_pages_keep_it(): void
    {
        config(['features.new_ui' => 'on']);
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);

        foreach ([self::PHONE, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0'] as $agent) {
            $this->actingAs($organiser->fresh())->withUnencryptedCookie('t1_theme', 'dark')->withHeaders(['User-Agent' => $agent])
                ->get(route('me.events.manage', $event->uuid))->assertOk()
                ->assertSee('data-t1-theme="light" data-t1-lock="light"', false)
                ->assertDontSee('data-t1-theme="dark"', false);
        }

        $this->actingAs($organiser->fresh())->withUnencryptedCookie('t1_theme', 'dark')->withHeaders(['User-Agent' => self::PHONE])
            ->get(route('me.events.show', $event->uuid))->assertOk()
            ->assertSee('data-t1-theme="dark"', false)
            ->assertDontSee('data-t1-lock', false);
    }
}

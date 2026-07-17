<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZZZDesktopHubSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_home_renders(): void
    {
        $user = User::factory()->create();
        $desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        $res = $this->actingAs($user)->withHeaders(['User-Agent' => $desktopUA])->get('/me');
        $res->assertOk();
        $res->assertSee('to-bar', false);
        $res->assertDontSee('data-shell-id="personal"', false);

        $mobileUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) Mobile/15E148';
        $mres = $this->actingAs($user)->withHeaders(['User-Agent' => $mobileUA])->get('/me');
        $mres->assertOk();
        $mres->assertSee('data-shell-id="personal"', false);
    }
}

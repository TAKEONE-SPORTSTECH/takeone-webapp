<?php

namespace Tests\Feature\Ui;

use App\Challenges\Models\Challenge;
use App\Challenges\Models\Duel;
use Tests\TestCase;

/** The new Challenge hub (/me/challenge while NEW_UI is on). */
class NewUiChallengeTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private function scene()
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy']);
        $me = $this->createUser(['full_name' => 'Ahmed Saleh']);
        $me->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $rival = $this->createUser(['full_name' => 'Khalid Mansoor']);
        $friend = $this->createUser(['full_name' => 'Ali Redha']);

        Duel::create(['challenger_id' => $rival->id, 'opponent_id' => $me->id, 'status' => 'pending', 'type' => 'athletic', 'discipline' => 'Most burpees in 3 minutes', 'stake_points' => 50]);
        Duel::create(['challenger_id' => $me->id, 'opponent_id' => $friend->id, 'status' => 'active', 'type' => 'fight', 'discipline' => 'BJJ roll', 'challenger_score' => 4, 'opponent_score' => 2]);
        Challenge::create(['tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'October mat hours', 'metric' => 'hours', 'goal' => 28, 'unit' => 'h', 'points' => 150,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(6), 'is_active' => true, 'color' => '#e11d48', 'icon' => 'bi-trophy']);

        return $me->fresh();
    }

    private function dump(string $name, string $html): void
    {
        if (getenv('T1_DUMP')) {
            @mkdir(storage_path('framework/testing/ui'), 0775, true);
            file_put_contents(storage_path("framework/testing/ui/{$name}.html"), $html);
        }
    }

    public function test_off_keeps_the_classic_hub(): void
    {
        config(['features.new_ui' => 'off']);
        $this->actingAs($this->scene())->get('/me/challenge')->assertOk()->assertDontSee('t1-rec', false);
    }

    public function test_the_new_hub_shows_invitations_duels_and_challenges(): void
    {
        config(['features.new_ui' => 'on']);

        $page = $this->actingAs($this->scene())->get('/me/challenge')->assertOk()
            ->assertSee('t1-rec', false)
            ->assertSee('Khalid Mansoor')
            ->assertSee('Most burpees in 3 minutes')
            ->assertSee("act('accept')", false)
            ->assertSee('BJJ roll')
            ->assertSee('October mat hours')
            ->assertSee(route('me.challenge.create'), false);
        $this->dump('challenge-desktop', $page->getContent());
    }

    public function test_the_new_hub_renders_in_the_phone_shell(): void
    {
        config(['features.new_ui' => 'on']);

        $page = $this->actingAs($this->scene())->withHeaders(['User-Agent' => self::PHONE])->get('/me/challenge')->assertOk()
            ->assertSee('t1-rec', false)->assertSee('id="shell-content"', false);
        $this->dump('challenge-phone', $page->getContent());
    }
}

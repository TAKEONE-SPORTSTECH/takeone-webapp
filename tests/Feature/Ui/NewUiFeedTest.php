<?php

namespace Tests\Feature\Ui;

use App\Clubs\Models\ClubTimelinePost;
use App\Members\Models\UserPost;
use Tests\TestCase;

/** The new Feed (/me while NEW_UI is on) — same data and behaviour, new page. */
class NewUiFeedTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

    private function scene()
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Victory Academy']);
        $me = $this->createUser(['full_name' => 'Ahmed Saleh']);
        $me->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        ClubTimelinePost::create(['tenant_id' => $club->id, 'body' => 'Grading day is set', 'status' => 'published', 'posted_at' => now()]);
        UserPost::create(['user_id' => $me->id, 'type' => 'text', 'body' => 'My first post']);
        UserPost::create(['user_id' => $me->id, 'type' => 'poll', 'body' => 'Which class?', 'poll' => ['question' => 'Which class?', 'options' => ['Kettlebells', 'Mobility']]]);

        return $me->fresh();
    }

    private function dump(string $name, string $html): void
    {
        if (getenv('T1_DUMP')) {
            @mkdir(storage_path('framework/testing/ui'), 0775, true);
            file_put_contents(storage_path("framework/testing/ui/{$name}.html"), $html);
        }
    }

    public function test_off_keeps_the_classic_feed(): void
    {
        config(['features.new_ui' => 'off']);
        $this->actingAs($this->scene())->get('/me')->assertOk()->assertDontSee('t1-composer', false);
    }

    public function test_the_new_feed_keeps_every_feed_behaviour_on_desktop(): void
    {
        config(['features.new_ui' => 'on']);

        $page = $this->actingAs($this->scene())->get('/me')->assertOk()
            ->assertSee('t1-composer', false)
            ->assertSee('newsFeed()', false)          // the platform's own feed component
            ->assertSee('submitPost()', false)
            ->assertSee('togglePoll()', false)
            ->assertSee('toggleHighlight()', false)
            ->assertSee('My first post')
            ->assertSee('Grading day is set')
            ->assertSee('Victory Academy');
        $this->dump('feed-desktop', $page->getContent());
    }

    public function test_the_new_feed_renders_in_the_phone_shell(): void
    {
        config(['features.new_ui' => 'on']);

        $page = $this->actingAs($this->scene())->withHeaders(['User-Agent' => self::PHONE])->get('/me')->assertOk()
            ->assertSee('t1-composer', false)->assertSee('id="shell-content"', false)->assertSee('t1-tabs', false);
        $this->dump('feed-phone', $page->getContent());
    }
}

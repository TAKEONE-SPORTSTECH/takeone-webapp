<?php

namespace Tests\Feature\Ui;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use Tests\TestCase;

/**
 * Ask TAKEONE (/me/ask). In tests no model is reachable, so the built-in
 * reader answers — the search and the plan are the same either way.
 */
class NewUiAskTest extends TestCase
{
    private function scene()
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Little Champions', 'currency' => 'BHD']);
        $swim = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Swimming', 'schedule' => [['day' => 'Saturday', 'time' => '16:00']]]);
        $kids = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Kids Multi-Sport', 'type' => 'multi', 'age_min' => 5, 'age_max' => 12, 'gender' => 'mixed', 'price' => 35, 'duration_months' => 1, 'is_active' => true]);
        $kids->activities()->attach($swim->id);
        ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Adults Boxing', 'type' => 'single', 'age_min' => 18, 'gender' => 'male', 'price' => 40, 'duration_months' => 1, 'is_active' => true]);

        return $this->createUser();
    }

    public function test_it_does_not_exist_with_the_flag_off(): void
    {
        config(['features.new_ui' => 'off']);
        $this->actingAs($this->scene())->postJson('/me/ask', ['message' => 'hi'])->assertNotFound();
    }

    public function test_the_page_renders_with_the_robot(): void
    {
        config(['features.new_ui' => 'on']);
        $page = $this->actingAs($this->scene())->get('/me/ask')->assertOk()->assertSee('t1Ask()', false)->assertSee('t1-bot', false);
        if (getenv('T1_DUMP')) { @mkdir(storage_path('framework/testing/ui'), 0775, true); file_put_contents(storage_path('framework/testing/ui/ask-desktop.html'), $page->getContent()); }
    }

    public function test_it_plans_a_week_for_three_kids_from_real_packages(): void
    {
        config(['features.new_ui' => 'on']);

        $r = $this->actingAs($this->scene())->postJson('/me/ask', ['message' => 'Build a mixed-activity package for my 3 kids, 6 years, 9 years and 12 years'])
            ->assertOk()->assertJsonPath('success', true)->json();

        $this->assertSame('Little Champions', $r['clubs'][0]['name']);
        $this->assertSame('Kids Multi-Sport', $r['clubs'][0]['packages'][0]['name']);
        $this->assertCount(3, $r['plan']['children']);
        $this->assertSame('BD 105', $r['plan']['total']);
        // the adults' class never reaches a children's plan
        $this->assertStringNotContainsString('Adults Boxing', json_encode($r));
    }

    public function test_it_says_plainly_when_no_coach_fits(): void
    {
        config(['features.new_ui' => 'on']);

        $r = $this->actingAs($this->scene())->postJson('/me/ask', ['message' => 'I need a female personal trainer, free 1 pm to 2 pm, budget 7 to 15 BD per hour'])
            ->assertOk()->json();

        $this->assertSame([], $r['coaches']);
        $this->assertStringContainsString('female personal trainers', $r['reply']);
    }

    public function test_it_rejects_an_oversized_message(): void
    {
        config(['features.new_ui' => 'on']);

        $this->actingAs($this->scene())->postJson('/me/ask', ['message' => str_repeat('a', 700)])->assertStatus(422);
    }
}

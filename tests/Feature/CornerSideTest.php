<?php

namespace Tests\Feature;

use App\Sports\Combat\CornerSide;
use Tests\TestCase;

/**
 * Which side the red corner stands on, per sport — the sport's default and the
 * super-admin's override from Platform Settings.
 */
class CornerSideTest extends TestCase
{
    public function test_each_sport_has_its_own_default(): void
    {
        $this->assertSame('right', CornerSide::red('karate'));
        $this->assertSame('right', CornerSide::red('bjj'));
        $this->assertSame('left', CornerSide::red('taekwondo'));
        $this->assertSame('left', CornerSide::red('unknown-sport'));
        $this->assertSame('left', CornerSide::red(null));
    }

    public function test_a_super_admin_can_flip_a_sport_and_put_it_back(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)
            ->putJson(route('admin.platform.settings.corner-side'), ['sport' => 'karate', 'side' => 'left'])
            ->assertOk()
            ->assertJsonPath('corner.side', 'left')
            ->assertJsonPath('corner.override', 'left');
        $this->assertSame('left', CornerSide::red('karate'));

        $this->actingAs($admin)
            ->putJson(route('admin.platform.settings.corner-side'), ['sport' => 'karate', 'side' => 'default'])
            ->assertOk()
            ->assertJsonPath('corner.override', null);
        $this->assertSame('right', CornerSide::red('karate'));
    }

    public function test_choosing_the_default_side_leaves_no_override(): void
    {
        CornerSide::setOverride('taekwondo', 'left');
        $this->assertNull(CornerSide::override('taekwondo'));
    }

    public function test_input_is_validated_and_only_a_super_admin_may_change_it(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->putJson(route('admin.platform.settings.corner-side'), ['sport' => 'chess', 'side' => 'left'])->assertStatus(422);
        $this->actingAs($admin)->putJson(route('admin.platform.settings.corner-side'), ['sport' => 'karate', 'side' => 'up'])->assertStatus(422);

        $this->actingAs($this->createUser())
            ->putJson(route('admin.platform.settings.corner-side'), ['sport' => 'karate', 'side' => 'left'])
            ->assertForbidden();
        $this->assertSame('right', CornerSide::red('karate'));
    }

    public function test_the_settings_page_shows_the_control(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)->get(route('admin.platform.settings'))
            ->assertOk()
            ->assertSee(__('platform.corner_side_heading'));
    }
}

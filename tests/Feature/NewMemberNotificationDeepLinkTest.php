<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use Tests\TestCase;

/**
 * The "Review registration" button in the new-member popup navigates to the
 * notification's action_url. It must point at the pending payment to verify,
 * not at a generic list.
 */
class NewMemberNotificationDeepLinkTest extends TestCase
{
    public function test_join_notification_deep_links_to_the_registrants_pending_payment(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $this->makeClubAdmin($owner, $club);

        $package = \App\Models\ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Monthly',
            'price' => 25,
            'duration_days' => 30,
        ]);

        $joiner = $this->createUser(['gender' => 'Male']);

        $this->actingAs($joiner)->postJson('/bh/clubs/join', [
            'club_id' => $club->id,
            'pay_later' => true,
            'registrants' => [[
                'type' => 'self',
                'name' => $joiner->name,
                'user_id' => $joiner->id,
                'package_id' => $package->id,
            ]],
        ])->assertOk();

        $notification = UserNotification::where('user_id', $owner->id)
            ->where('type', 'new_member')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification, 'The club owner should be notified of the registration.');
        $this->assertStringContainsString("/admin/club/{$club->slug}/financials", $notification->action_url);
        $this->assertStringContainsString("member={$joiner->uuid}", $notification->action_url);
        $this->assertStringEndsWith('#collect', $notification->action_url);

        // And the link must actually resolve to that member's outstanding row.
        $response = $this->actingAs($owner)->get($notification->action_url)->assertOk();
        $this->assertNotEmpty($response->viewData('focusSubscriptionIds'));
    }
}

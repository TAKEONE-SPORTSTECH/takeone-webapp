<?php

namespace Tests\Feature;

use App\Models\ClubMemberSubscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The "new member registration" notification's "Review registration" button used
 * to drop the admin on an unfiltered members list, leaving them to hunt for the
 * payment to verify. It now deep-links to financials focused on that member's
 * outstanding row: /admin/club/{club}/financials?member={uuid}#collect
 *
 * The uuid is resolved by FILTERING the club's already-authorized pending rows,
 * so it can only ever focus a row this admin can already see.
 */
class FinancialsPendingDeepLinkTest extends TestCase
{
    private function pendingSubscription(Tenant $club, User $member, array $attrs = []): ClubMemberSubscription
    {
        return ClubMemberSubscription::create(array_merge([
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'type' => 'regular',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'amount_due' => 25,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_test' => $club->is_test_mode,
        ], $attrs));
    }

    public function test_member_uuid_focuses_that_members_outstanding_subscription(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $sub = $this->pendingSubscription($club, $member);

        $response = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials?member={$member->uuid}")
            ->assertOk();

        $this->assertSame([$sub->id], $response->viewData('focusSubscriptionIds'));

        // ...and it must actually reach the client, or the focus silently no-ops.
        $this->assertStringContainsString("[{$sub->id}]", $response->getContent());
        $this->assertStringContainsString("data-sub-id=\"{$sub->id}\"", $response->getContent());
    }

    public function test_the_mobile_view_also_receives_the_focus_ids(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $sub = $this->pendingSubscription($club, $member);

        $response = $this->actingAs($owner)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'])
            ->get("/admin/club/{$club->slug}/financials?member={$member->uuid}")
            ->assertOk();

        $this->assertSame([$sub->id], $response->viewData('focusSubscriptionIds'));
        // @js() compiles to JSON.parse('…'), so match what actually lands in the page.
        $this->assertStringContainsString("focusSubIds: JSON.parse('[{$sub->id}]')", $response->getContent());
    }

    public function test_other_members_rows_are_not_focused(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $sub = $this->pendingSubscription($club, $member);

        $other = $this->createUser();
        $this->pendingSubscription($club, $other);

        $response = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials?member={$member->uuid}")
            ->assertOk();

        // Only the requested member's row — not every pending row on the page.
        $this->assertSame([$sub->id], $response->viewData('focusSubscriptionIds'));
    }

    /**
     * Security: the uuid is a focus hint, never a lookup key. A member of ANOTHER
     * club must focus nothing here — and the page must not behave differently from
     * an unknown uuid, so it can't be used to probe which uuids exist.
     */
    public function test_a_member_of_another_club_focuses_nothing(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $otherOwner = $this->createUser();
        $otherClub = $this->createClub($otherOwner);
        $outsider = $this->createUser();
        $this->pendingSubscription($otherClub, $outsider);

        $foreign = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials?member={$outsider->uuid}")
            ->assertOk();

        $unknown = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials?member=".fake()->uuid())
            ->assertOk();

        $this->assertSame([], $foreign->viewData('focusSubscriptionIds'));
        $this->assertSame([], $unknown->viewData('focusSubscriptionIds'));
    }

    public function test_a_paid_subscription_is_never_focused(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $this->pendingSubscription($club, $member, ['payment_status' => 'paid', 'status' => 'active']);

        $response = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials?member={$member->uuid}")
            ->assertOk();

        $this->assertSame([], $response->viewData('focusSubscriptionIds'));
    }

    public function test_the_page_still_renders_without_the_deep_link(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $this->pendingSubscription($club, $member);

        $response = $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/financials")
            ->assertOk();

        $this->assertSame([], $response->viewData('focusSubscriptionIds'));
    }
}

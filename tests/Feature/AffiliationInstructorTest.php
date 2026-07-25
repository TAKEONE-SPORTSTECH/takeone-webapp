<?php

namespace Tests\Feature;

use App\Models\ClubAffiliation;
use App\Models\User;
use Tests\TestCase;

/**
 * Members can attach instructors to a club affiliation — a linked platform member
 * (by uuid) or a free-text name. Stored on the affiliation's `coaches` JSON.
 */
class AffiliationInstructorTest extends TestCase
{
    private function affiliationFor(User $member): ClubAffiliation
    {
        return ClubAffiliation::create([
            'member_id' => $member->id,
            'tenant_id' => null,
            'club_name' => 'Old Dojang',
            'start_date' => now()->subYears(3)->toDateString(),
        ]);
    }

    public function test_a_free_text_instructor_can_be_added(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/instructors", ['name' => 'Master Splinter'])
            ->assertOk()
            ->assertJsonPath('instructors.0.name', 'Master Splinter')
            ->assertJsonPath('instructors.0.linked', false);

        $this->assertSame([['name' => 'Master Splinter', 'user_id' => null]], $aff->fresh()->coaches);
    }

    public function test_a_member_instructor_links_by_uuid_and_uses_their_real_name(): void
    {
        $member = $this->createUser();
        $coach = $this->createUser(['full_name' => 'Coach Real']);
        $aff = $this->affiliationFor($member);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/instructors", [
                'name' => 'spoofed name', 'user_uuid' => $coach->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('instructors.0.name', 'Coach Real')   // real name wins
            ->assertJsonPath('instructors.0.linked', true);

        $this->assertSame($coach->id, $aff->fresh()->coaches[0]['user_id']);
    }

    public function test_duplicate_instructors_are_ignored(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $post = fn () => $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/instructors", ['name' => 'Same Coach']);
        $post()->assertOk();
        $post()->assertOk();

        $this->assertCount(1, $aff->fresh()->coaches);
    }

    public function test_an_instructor_can_be_removed_by_index(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);
        $aff->update(['coaches' => [['name' => 'A', 'user_id' => null], ['name' => 'B', 'user_id' => null]]]);

        $this->actingAs($member)
            ->deleteJson("/member/{$member->id}/affiliations/{$aff->id}/instructors", ['index' => 0])
            ->assertOk()
            ->assertJsonPath('instructors.0.name', 'B');

        $this->assertSame([['name' => 'B', 'user_id' => null]], $aff->fresh()->coaches);
    }

    public function test_legacy_string_coaches_are_still_readable(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);
        $aff->update(['coaches' => ['Old Name One', 'Old Name Two']]);   // legacy flat strings

        $this->assertSame([
            ['name' => 'Old Name One', 'user_id' => null],
            ['name' => 'Old Name Two', 'user_id' => null],
        ], $aff->fresh()->instructorList());
    }

    public function test_a_stranger_cannot_add_an_instructor(): void
    {
        $member = $this->createUser();
        $stranger = $this->createUser();
        $aff = $this->affiliationFor($member);

        $this->actingAs($stranger)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/instructors", ['name' => 'x'])
            ->assertNotFound();   // authorizeForMember denies via 404
    }

    public function test_instructor_search_is_name_only_and_gated(): void
    {
        $member = $this->createUser(['full_name' => 'Findable Person']);
        $searcher = $this->createUser();

        // Too-short query returns nothing.
        $this->actingAs($searcher)
            ->getJson("/member/{$searcher->id}/instructor-search?q=F")
            ->assertOk()->assertJsonPath('results', []);

        $this->actingAs($searcher)
            ->getJson("/member/{$searcher->id}/instructor-search?q=Findable")
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Findable Person');

        // A stranger cannot search on someone else's behalf.
        $this->actingAs($searcher)
            ->getJson("/member/{$member->id}/instructor-search?q=Findable")
            ->assertNotFound();
    }
}

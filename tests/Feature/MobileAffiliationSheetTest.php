<?php

namespace Tests\Feature;

use App\Models\ClubAffiliation;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The mobile affiliation detail sheet resolves a LINKED-member instructor to their
 * system avatar + PUBLIC profile (people.show); a free-text instructor stays an
 * unknown-user avatar with no link.
 */
class MobileAffiliationSheetTest extends TestCase
{
    private function mobileGet(string $uri)
    {
        return $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')->get($uri);
    }

    public function test_skill_duration_is_accumulated_enrolled_time_for_a_system_club(): void
    {
        $member = $this->createUser();
        $owner = $this->createUser();
        $club = Tenant::create(['owner_user_id' => $owner->id, 'club_name' => 'Real Club', 'slug' => 'real-'.uniqid(), 'status' => 'active']);
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => $club->id, 'club_name' => 'Real Club',
            'start_date' => '2024-01-01',
        ]);
        $pkg = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Monthly', 'price' => 10, 'duration_days' => 30]);

        // Enrolled 1 month, 5-month gap, then 4 months = 5 months of actual enrolment.
        foreach ([['2024-01-01', '2024-02-01'], ['2024-07-01', '2024-11-01']] as [$st, $en]) {
            ClubMemberSubscription::create([
                'tenant_id' => $club->id, 'user_id' => $member->id, 'club_affiliation_id' => $aff->id,
                'package_id' => $pkg->id, 'type' => 'regular', 'status' => 'expired',
                'payment_status' => 'paid', 'amount_due' => 0, 'start_date' => $st, 'end_date' => $en,
            ]);
        }
        // A skill in this club — its shown duration must be the enrolled 5 months, not the 10-month span.
        $aff->skillAcquisitions()->create([
            'user_id' => $member->id, 'skill_name' => 'Boxing', 'proficiency_level' => 'advanced', 'duration_months' => 99,
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $skill = $data[$aff->id]['skills'][0];
        $this->assertSame('5 months', $skill['duration']);   // 1 + 4, gap excluded
    }

    public function test_manual_record_skill_keeps_its_full_recorded_span(): void
    {
        $member = $this->createUser();
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Old Dojang (pre-system)',
            'start_date' => '2000-01-01',
        ]);
        // No subscriptions → manual record → keep the skill's own recorded duration (24 months).
        $aff->skillAcquisitions()->create([
            'user_id' => $member->id, 'skill_name' => 'Poomsae', 'proficiency_level' => 'expert', 'duration_months' => 24,
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $this->assertSame('2 years', $data[$aff->id]['skills'][0]['duration']);
    }

    /** Pull the JSON handed to affiliationSheet(JSON.parse('…')). */
    private function sheetData(string $html): array
    {
        $this->assertMatchesRegularExpression("/affiliationSheet\\(JSON\\.parse\\('.*'\\)\\)/", $html);
        preg_match("/affiliationSheet\\(JSON\\.parse\\('((?:[^'\\\\]|\\\\.)*)'\\)\\)/", $html, $m);
        $json = json_decode(json_decode('"'.$m[1].'"'), true);   // unescape the JS string, then the JSON
        return $json;
    }

    public function test_a_linked_member_instructor_shows_system_avatar_and_public_profile_link(): void
    {
        $member = $this->createUser();
        $coach = $this->createUser(['full_name' => 'Master Kim', 'profile_picture' => 'people/x/profile/pic.webp']);
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Dojang',
            'start_date' => now()->subYear()->toDateString(),
            'coaches' => [
                ['name' => 'ignored', 'user_id' => $coach->id],   // linked member
                ['name' => 'Free Text Coach', 'user_id' => null], // free text
            ],
        ]);

        $html = $this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent();
        $data = $this->sheetData($html);
        $instructors = $data[$aff->id]['instructors'];

        // Linked member → real name, system avatar, PUBLIC profile url.
        $this->assertSame('Master Kim', $instructors[0]['name']);
        $this->assertStringContainsString('people/x/profile/pic.webp', $instructors[0]['avatar']);
        $this->assertStringContainsString('/people/'.$coach->uuid, $instructors[0]['url']);

        // Free text → no avatar (unknown fallback), no link.
        $this->assertSame('Free Text Coach', $instructors[1]['name']);
        $this->assertNull($instructors[1]['avatar']);
        $this->assertNull($instructors[1]['url']);
    }

    public function test_a_free_text_instructor_matching_a_system_member_links_to_their_public_profile(): void
    {
        $member = $this->createUser();
        $coach = $this->createUser(['full_name' => 'Abdulla Isa Al Doy', 'profile_picture' => 'people/y/profile/p.webp']);
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Dojang',
            'start_date' => now()->subYear()->toDateString(),
            'coaches' => ['Abdulla Isa Al Doy'],   // legacy free-text string, no user_id
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $ins = $data[$aff->id]['instructors'][0];

        $this->assertSame('Abdulla Isa Al Doy', $ins['name']);
        $this->assertStringContainsString('/people/'.$coach->uuid, $ins['url']);          // links to public profile
        $this->assertStringContainsString('people/y/profile/p.webp', $ins['avatar']);      // system avatar
    }

    public function test_an_ambiguous_name_is_not_linked(): void
    {
        $member = $this->createUser();
        // Two members share the exact name -> must NOT auto-link (can\'t tell which one).
        $this->createUser(['full_name' => 'John Smith']);
        $this->createUser(['full_name' => 'John Smith']);
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Dojang',
            'start_date' => now()->subYear()->toDateString(),
            'coaches' => [['name' => 'John Smith', 'user_id' => null]],
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $ins = $data[$aff->id]['instructors'][0];
        $this->assertSame('John Smith', $ins['name']);
        $this->assertNull($ins['url']);
        $this->assertNull($ins['avatar']);
    }

    public function test_an_unknown_name_stays_plain(): void
    {
        $member = $this->createUser();
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Dojang',
            'start_date' => now()->subYear()->toDateString(),
            'coaches' => [['name' => 'Nobody In System', 'user_id' => null]],
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $ins = $data[$aff->id]['instructors'][0];
        $this->assertNull($ins['url']);
        $this->assertNull($ins['avatar']);
    }

    public function test_a_linked_member_without_a_photo_still_links_but_has_no_avatar(): void
    {
        $member = $this->createUser();
        $coach = $this->createUser(['full_name' => 'Coach NoPhoto', 'profile_picture' => null]);
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Dojang',
            'start_date' => now()->subYear()->toDateString(),
            'coaches' => [['name' => 'x', 'user_id' => $coach->id]],
        ]);

        $data = $this->sheetData($this->actingAs($member)->mobileGet("/member/{$member->uuid}")->assertOk()->getContent());
        $ins = $data[$aff->id]['instructors'][0];

        $this->assertSame('Coach NoPhoto', $ins['name']);
        $this->assertNull($ins['avatar']);                                   // → unknown-user fallback in the UI
        $this->assertStringContainsString('/people/'.$coach->uuid, $ins['url']); // still links to public profile
    }
}

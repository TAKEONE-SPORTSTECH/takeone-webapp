<?php

namespace Tests\Feature\Clubs;

use App\Clubs\Models\ClubAchievement;
use App\Clubs\Models\ClubFacility;
use App\Clubs\Models\ClubGalleryImage;
use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\ClubTimelinePost;
use App\Clubs\Models\ClubTimelinePostComment;
use App\Clubs\Models\Tenant;
use App\Shop\Models\ClubPerk;
use App\Shop\Models\ClubProduct;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The club front page (clubs::public.show / clubs::public.mobile.show), rebuilt on
 * the "Club Pages & Design" drafts. Both files must render for a stranger, a member
 * and the owner, with a full club and with an empty one — a section with no data
 * is simply not drawn, never a sample.
 */
class ClubFrontPageTest extends TestCase
{
    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

    private function url(Tenant $club): string
    {
        return '/'.strtolower($club->country ?: 'bh').'/clubs/'.$club->slug;
    }

    /** @return array{0: Tenant, 1: \App\Members\Models\User, 2: \App\Members\Models\User} */
    private function fullClub(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slogan' => 'Confidence starts here', 'address' => 'Road 1238, Dar Kulayb', 'country' => 'BH']);
        $coach = $this->createUser(['full_name' => 'Coach Sami Manea']);

        $instructor = ClubInstructor::create(['tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Head coach']);
        $packageId = DB::table('club_packages')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Kids Fundamentals', 'type' => 'single', 'gender' => 'mixed',
            'age_min' => 5, 'age_max' => 10, 'price' => 35, 'duration_months' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('club_packages')->insert([
            'tenant_id' => $club->id, 'name' => 'Adults Mastery', 'type' => 'single', 'gender' => 'mixed',
            'age_min' => 18, 'price' => 40, 'duration_months' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('club_activities')->insertGetId(['tenant_id' => $club->id, 'name' => 'Taekwondo', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('club_package_activities')->insert([
            'package_id' => $packageId, 'activity_id' => $activityId, 'instructor_id' => $instructor->id,
            'schedule' => json_encode([['day' => 'monday', 'start_time' => '16:00', 'end_time' => '17:00']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        ClubFacility::create(['tenant_id' => $club->id, 'name' => 'Main Training Hall', 'address' => 'Road 1238', 'is_available' => true]);
        ClubAchievement::create(['tenant_id' => $club->id, 'title' => 'Heroes Championship', 'location' => 'Bangkok', 'date_label' => 'Jul 2025',
            'medals_gold' => 3, 'medals_silver' => 2, 'tag' => 'Champions', 'status' => 'active']);
        $post = ClubTimelinePost::create(['tenant_id' => $club->id, 'body' => "Grading day results\nTwelve new belts.", 'category' => 'Club',
            'status' => 'published', 'posted_at' => now()->subDay()]);
        ClubTimelinePostComment::create(['post_id' => $post->id, 'user_id' => $owner->id, 'body' => 'Proud of everyone']);
        ClubPerk::create(['tenant_id' => $club->id, 'title' => 'Healthy Calorie 20% off', 'badge' => 'Partner perk', 'icon' => 'bi-gift', 'perk_type' => 'code', 'perk_value' => 'HC20', 'status' => 'active']);
        ClubProduct::create(['tenant_id' => $club->id, 'name' => 'Club hoodie', 'category' => 'merch', 'price' => 15, 'status' => 'published', 'availability' => 'in']);
        ClubGalleryImage::create(['tenant_id' => $club->id, 'image_path' => 'clubs/test/gallery/a.jpg', 'caption' => 'Life on the mat', 'uploaded_by' => $owner->id]);

        return [$club, $owner, $coach];
    }

    public function test_full_club_renders_every_section_on_desktop_and_mobile_for_a_stranger(): void
    {
        [$club, , $coach] = $this->fullClub();

        foreach ([false, true] as $phone) {
            $req = $phone ? $this->withHeaders(['User-Agent' => self::PHONE]) : $this;
            $res = $req->get($this->url($club));
            if ($res->status() !== 200) {
                dump(substr(strip_tags($res->getContent()), 0, 3000));
            }
            $res->assertOk()
                ->assertSee('id="cp-json"', false)
                ->assertSee('Confidence', false)
                ->assertSee('Kids Fundamentals')
                ->assertSee('Adults Mastery')
                ->assertSee(route('trainer.show', $coach->id), false)
                ->assertSee('Coach Sami Manea')
                ->assertSee('Main Training Hall')
                ->assertSee('Heroes Championship')
                ->assertSee('Grading day results')
                ->assertSee('Healthy Calorie 20% off')
                ->assertSee('Club hoodie')
                ->assertSee('Life on the mat')
                ->assertSee(__('clubs::page.faq_title'))
                // The perk value is only ever handed out by the collect endpoint.
                ->assertDontSee('HC20');
        }
    }

    public function test_an_activity_links_to_its_directory_page(): void
    {
        [$club] = $this->fullClub();
        $entry = \App\Platform\Models\ActivityCatalog::contribute(['name' => 'Taekwondo']);

        $this->get($this->url($club))->assertOk()->assertSee(route('activity.show', $entry->uuid), false);
        $this->withHeaders(['User-Agent' => self::PHONE])->get($this->url($club))->assertOk()->assertSee(route('activity.show', $entry->uuid), false);
    }

    public function test_an_empty_club_draws_no_sample_sections(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);

        foreach ([false, true] as $phone) {
            $req = $phone ? $this->withHeaders(['User-Agent' => self::PHONE]) : $this;
            $req->get($this->url($club))
                ->assertOk()
                ->assertSee('Test Club')
                ->assertDontSee(__('clubs::page.medals_eyebrow'))
                ->assertDontSee(__('clubs::page.news_title'))
                ->assertDontSee(__('clubs::page.perks_eyebrow'))
                ->assertDontSee(__('clubs::page.shop_eyebrow'))
                ->assertDontSee('unsplash.com', false);
        }
    }

    public function test_member_and_owner_views_render(): void
    {
        [$club, $owner] = $this->fullClub();
        $member = $this->createUser();

        foreach ([false, true] as $phone) {
            $headers = $phone ? ['User-Agent' => self::PHONE] : [];
            $this->actingAs($member)->withHeaders($headers)->get($this->url($club))
                ->assertOk()
                ->assertSee('selectPackageModalWrap', false)
                ->assertDontSee(__('clubs::page.edit_club'));

            $this->actingAs($owner)->withHeaders($headers)->get($this->url($club))
                ->assertOk()
                ->assertSee(__('clubs::page.edit_club'));
        }
    }

    public function test_trainer_desktop_page_renders_for_a_stranger_and_a_member(): void
    {
        [$club, , $coach] = $this->fullClub();
        $coach->forceFill(['bio' => 'Twenty years on the mat.'])->save();
        $member = $this->createUser();

        // /t/ is the public door; /trainer/ is the signed-in one.
        foreach ([[null, '/t/'.$coach->id], [$member, '/trainer/'.$coach->id]] as [$who, $url]) {
            $res = $who ? $this->actingAs($who)->get($url) : $this->get($url);
            if ($res->status() !== 200) {
                dump(substr(strip_tags($res->getContent()), 0, 3000));
            }
            $res->assertOk()
                ->assertSee('Coach Sami Manea')
                ->assertSee('Twenty years on the mat.')
                ->assertSee('Taekwondo')
                ->assertSee($club->club_name)
                ->assertSee(__('trainer.tp_classes_title'));
        }
    }

    public function test_desktop_keeps_the_club_in_numbers_statistics(): void
    {
        [$club] = $this->fullClub();

        $this->get($this->url($club))->assertOk()
            ->assertSee(__('clubs::page.in_numbers'))
            ->assertSee('donutNationalities', false)
            ->assertSee('barMonthlyMembers', false);
    }
}

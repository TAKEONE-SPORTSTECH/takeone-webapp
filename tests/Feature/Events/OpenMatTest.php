<?php

namespace Tests\Feature\Events;

use App\Events\OpenMat\OpenMat;
use App\Events\OpenMat\OpenMatCorner;
use App\Models\ClubEvent;
use App\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A scoreboard for a fight nobody planned — for every sport that has one.
 *
 * The promise of an open mat is that a member goes to one short address and is
 * ON a scored mat: no event to create, no draw, no division, no entry list.
 * These tests hold that promise for each sport in turn, end to end — open the
 * mat, put a name in each corner, start the bout, and reach the sport's own
 * scoring table with a bout loaded on it.
 *
 * The loop over OpenMat::sports() is the point, not an economy. Brazilian
 * Jiu-Jitsu was absent from this feature for one reason: its wall-screen fleet
 * is called HallScreen\ScreenDevice where the other two say
 * CourtDisplay\CourtDisplayDevice, and the registry keyed on the class name.
 * Every other seam it already satisfied. A test that names one sport would let
 * the next sport be forgotten the same way, so this one asserts the whole
 * registry can do the whole job.
 *
 * ⚠️ Run `php artisan config:clear` first — see CLAUDE.md.
 */
class OpenMatTest extends TestCase
{
    use RefreshDatabase;

    private const MAT = 'Mat 1';

    /** A member with a club, which is the only thing opening a mat requires. */
    private function member(): User
    {
        $user = $this->createUser();
        $club = $this->createClub($user, ['country' => 'BH', 'currency' => 'BHD']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user;
    }

    public function test_every_registered_sport_can_be_opened_filled_and_scored(): void
    {
        $this->assertNotEmpty(OpenMat::sports(), 'No sport offers a mat, so the feature does nothing.');

        foreach (OpenMat::sports() as $sport) {
            $user = $this->member();

            // 1. One address, and you are on a mat.
            $response = $this->actingAs($user)->get('/openmat/'.$sport);
            $response->assertRedirect();

            $uuid = $this->uuidFrom($response->headers->get('Location'));
            $event = ClubEvent::where('uuid', $uuid)->first();

            $this->assertNotNull($event, "[{$sport}] /openmat/{$sport} did not open a mat.");
            $this->assertSame($sport, (string) $event->sport, "[{$sport}] opened on the wrong sport.");
            $this->assertSame(OpenMat::TYPE, (string) $event->event_type, "[{$sport}] is not an open mat.");

            // 2. The console the operator actually holds.
            $this->actingAs($user)->get('/me/events/'.$uuid.'/manage')->assertOk();

            // 3. A name in each corner — no account needed for either of them.
            foreach ([OpenMatCorner::SIDE_AKA => 'Red Guest', OpenMatCorner::SIDE_AO => 'Blue Guest'] as $side => $name) {
                $this->actingAs($user)
                    ->postJson('/me/events/'.$uuid.'/actions/place_guest', [
                        // The console posts the mat as `mat`; `court` is what the
                        // bout row calls it. Same value, two names, and only this
                        // one is read here.
                        'mat' => self::MAT, 'side' => $side, 'name' => $name,
                    ])
                    ->assertOk()
                    ->assertJson(['success' => true]);
            }

            // 4. Start it. There is no draw behind this bout, by design.
            $this->actingAs($user)
                ->postJson('/me/events/'.$uuid.'/actions/start_bout', ['mat' => self::MAT])
                ->assertOk()
                ->assertJson(['success' => true]);

            $match = EventMatch::where('event_id', $event->id)->where('court', self::MAT)->first();
            $this->assertNotNull($match, "[{$sport}] start_bout created no bout.");
            $this->assertSame('Red Guest', (string) $match->a_name, "[{$sport}] the red corner did not reach the bout.");
            $this->assertSame('Blue Guest', (string) $match->b_name, "[{$sport}] the blue corner did not reach the bout.");

            // 5. The sport's OWN scoring table, on an event with no draw.
            $this->actingAs($user)
                ->get(route($sport.'-scoreboard.control', ['event' => $uuid, 'mat' => self::MAT]))
                ->assertOk();
        }
    }

    /**
     * The wall-screen panel the console draws.
     *
     * Mats come from the mat, not from its bouts — a screen is paired to Mat 2
     * before anybody has stood on it. A sport whose fleet the registry cannot
     * find returns null here, which is exactly how BJJ used to fail: silently,
     * as "this type has no wall boards".
     */
    public function test_every_registered_sport_offers_its_wall_screens(): void
    {
        foreach (OpenMat::sports() as $sport) {
            $user = $this->member();

            $uuid = $this->uuidFrom(
                $this->actingAs($user)->get('/openmat/'.$sport)->headers->get('Location')
            );

            $event = ClubEvent::where('uuid', $uuid)->firstOrFail();
            $panel = app(\App\Events\EventTypeRegistry::class)->for($event)?->hallScreens($event);

            $this->assertIsArray($panel, "[{$sport}] offers no hall-screen panel, so its screens cannot be paired.");
            $this->assertNotEmpty($panel['mats'] ?? [], "[{$sport}] offers no mat to pair a screen to.");
        }
    }

    /** Asking twice resumes the same mat rather than opening a second one. */
    public function test_opening_the_same_sport_twice_resumes_the_same_mat(): void
    {
        $user = $this->member();

        $first = $this->uuidFrom($this->actingAs($user)->get('/openmat/bjj')->headers->get('Location'));
        $second = $this->uuidFrom($this->actingAs($user)->get('/openmat/bjj')->headers->get('Location'));

        $this->assertSame($first, $second, 'A second visit opened a second mat and would strand any paired screen.');
        $this->assertSame(1, ClubEvent::where('event_type', OpenMat::TYPE)->count());
    }

    /** Each sport keeps its own mat: a karate mat is not what a BJJ request finds. */
    public function test_each_sport_gets_its_own_mat(): void
    {
        $user = $this->member();

        $karate = $this->uuidFrom($this->actingAs($user)->get('/openmat/karate')->headers->get('Location'));
        $bjj = $this->uuidFrom($this->actingAs($user)->get('/openmat/bjj')->headers->get('Location'));

        $this->assertNotSame($karate, $bjj);
        $this->assertSame('karate', (string) ClubEvent::where('uuid', $karate)->value('sport'));
        $this->assertSame('bjj', (string) ClubEvent::where('uuid', $bjj)->value('sport'));
    }

    /**
     * A sport with no scoring table never opens a mat.
     *
     * The route constraint refuses it, so this is a miss at the ROUTER — which
     * the platform's own 404 handler then turns into a bounce-back-with-a-toast
     * for browser navigation and a real 404 for JSON, exactly as it does
     * everywhere else (CLAUDE.md → "403s Redirect on Web, Return JSON on API",
     * and the 404 handler that mirrors it). What matters here is the second
     * assertion: whatever the caller is told, no mat was opened.
     */
    public function test_a_sport_with_no_mat_is_refused(): void
    {
        $user = $this->member();

        foreach (['swimming', 'football', 'nonsense'] as $sport) {
            $this->actingAs($user)->get('/openmat/'.$sport)->assertRedirect();
            $this->actingAs($user)->getJson('/openmat/'.$sport)->assertNotFound();
        }

        $this->assertSame(0, ClubEvent::where('event_type', OpenMat::TYPE)->count());
    }

    /** Signing in is required: a corner puts a name on a wall screen. */
    public function test_a_guest_cannot_open_a_mat(): void
    {
        $this->get('/openmat/bjj')->assertRedirect('/login');
    }

    private function uuidFrom(?string $location): string
    {
        $this->assertNotNull($location, 'Opening a mat did not redirect anywhere.');

        // /me/events/{uuid}/manage
        return explode('/', trim(parse_url($location, PHP_URL_PATH), '/'))[2];
    }
}

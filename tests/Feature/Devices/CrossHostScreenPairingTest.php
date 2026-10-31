<?php

namespace Tests\Feature\Devices;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The whole hall journey, end to end: a screen opens /screen, the organiser
 * scans it from the event console, and the screen lands on the board it was
 * given — scoring table, scoreboard, upcoming matches — and a camera pairs.
 *
 * Every pairing is sent with `from_host` set to the OTHER host, because stage
 * and production share one database and that was the case failing on
 * 31 Oct 2026: a perfectly good code refused for the host that printed it.
 */
class CrossHostScreenPairingTest extends TestCase
{
    private const MAT = 'Mat 1';

    private function event(string $sport, User $organiser): ClubEvent
    {
        $club = $this->createClub($organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
            'title' => 'Cross Host Open',
            'event_type' => 'championship',
            'sport' => $sport,
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $category = EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Male -75 kg', 'sort_order' => 1]);

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $category->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0, 'match_no' => 1,
            'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => self::MAT, 'status' => 'upcoming',
        ]);

        return $event;
    }

    /** Open /screen the way a television does; return its token and code. */
    private function waitingScreen(string $host): array
    {
        $location = (string) $this->get("https://{$host}/screen")->assertRedirect()->headers->get('Location');
        $token = basename(parse_url($location, PHP_URL_PATH));

        $code = \App\Events\Support\PendingScreen::where('token_hint', substr($token, 0, 6))->latest('id')->value('pairing_code');
        $this->assertNotNull($code, 'the waiting room issued no code');

        return [$token, $code];
    }

    /** Follow the screen's address until something renders. */
    private function land(string $url): array
    {
        $host = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);
        $seen = [];

        for ($hop = 0; $hop < 8; $hop++) {
            $this->assertNotContains($url, $seen, 'redirect loop: '.implode(' -> ', $seen));
            $seen[] = $url;

            $res = $this->get($url);

            if (! $res->isRedirect()) {
                return [$res->getStatusCode(), $url];
            }

            $next = (string) $res->headers->get('Location');
            $url = str_starts_with($next, 'http') ? $next : $host.$next;
        }

        $this->fail('no page rendered: '.implode(' -> ', $seen));
    }

    public static function journeys(): array
    {
        $rows = [];

        foreach (['karate', 'taekwondo', 'bjj'] as $sport) {
            foreach (['control', 'bout', 'queue', 'follow'] as $surface) {
                // Both directions: a screen on one host, the console on the other.
                foreach ([['takeone.bh', 'stage.takeone.bh'], ['stage.takeone.bh', 'takeone.bh']] as [$screen, $console]) {
                    $rows["$sport $surface: $screen screen, $console console"] = [$sport, $surface, $screen, $console];
                }
            }
        }

        return $rows;
    }

    /** @dataProvider journeys */
    public function test_a_scanned_screen_reaches_its_board(string $sport, string $surface, string $screenHost, string $consoleHost): void
    {
        $organiser = $this->createUser();
        $event = $this->event($sport, $organiser);

        [$token, $code] = $this->waitingScreen($screenHost);

        $this->actingAs($organiser)->postJson("https://{$consoleHost}/me/events/{$event->uuid}/screens", [
            'code' => $code, 'court' => self::MAT, 'surface' => $surface, 'from_host' => $screenHost,
        ])->assertOk()->assertJsonPath('success', true);

        // The television holds no session.
        auth()->logout();
        $this->flushSession();

        [$status, $landed] = $this->land("https://{$screenHost}/screen/{$token}");

        $this->assertSame(200, $status, "landed on {$landed}");
        $this->assertNotSame("https://{$screenHost}/screen/{$token}", $landed, 'the screen never left the waiting room');
    }

    public static function directions(): array
    {
        return [
            'takeone.bh camera, stage console' => ['takeone.bh', 'stage.takeone.bh'],
            'stage camera, takeone.bh console' => ['stage.takeone.bh', 'takeone.bh'],
        ];
    }

    /** @dataProvider directions */
    public function test_a_camera_scanned_from_the_other_host_pairs(string $cameraHost, string $consoleHost): void
    {
        $organiser = $this->createUser();
        $event = $this->event('karate', $organiser);

        $enrolled = $this->postJson("https://{$cameraHost}/camera/enroll", ['device_name' => 'Tripod', 'app_version' => '1.0'])->assertCreated();

        $this->actingAs($organiser)->postJson("https://{$consoleHost}/me/events/{$event->uuid}/screens", [
            'code' => $enrolled->json('code'), 'court' => self::MAT, 'surface' => 'camera', 'from_host' => $cameraHost,
        ])->assertOk()->assertJsonPath('success', true);

        $this->getJson('/camera/'.$enrolled->json('token').'/config')->assertOk();
    }
}

<?php

namespace Tests\Feature\Certificates;

use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Models\CertificateTemplate;
use Ahl\Certificates\Support\Formats;
use Ahl\Certificates\Support\Layout;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventBadges;
use App\Mcp\Tools\EventBadgesTool;
use App\Members\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Officials' badges — the printing engine's second format (Ahl\Certificates\Support\
 * Formats `badge`, App\Events\Support\EventBadges).
 *
 * What this protects: a badge is an A6 page and a certificate is still A4; every
 * official has a badge with nothing to approve; the print-shop file lays four to an A4
 * sheet; only the event's managers reach the badges, by list, by token or by the bundle;
 * the QR verifies a current official and, once they are removed, answers exactly as a
 * code that never existed; the design room works for badges and keeps a badge's way up.
 */
class OfficialBadgesTest extends TestCase
{
    private User $organiser;

    private Tenant $club;

    private ClubEvent $event;

    /** @var list<EventOfficial> */
    private array $officials = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['certificates.enabled' => true]);

        $this->organiser = $this->createUser();
        $this->club = $this->createClub($this->organiser, ['country' => 'BH']);
        $this->event = ClubEvent::create([
            'tenant_id' => $this->club->id, 'created_by' => $this->organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
            'location' => 'Isa Sports City',
        ]);
        foreach (['Referee 1', 'Referee 2', 'Table', 'Doctor', 'Steward'] as $i => $job) {
            $this->officials[] = EventOfficial::create([
                'event_id' => $this->event->id,
                'user_id' => $this->createUser(['full_name' => 'Official '.($i + 1)])->id,
                'role' => EventOfficial::ROLE_OTHER,
                'role_label' => $job,
                'assigned_by' => $this->organiser->id,
            ]);
        }
    }

    private function key(): array
    {
        return ['key' => $this->event->uuid];
    }

    private function mcp(string $tool, User $as, array $args): array
    {
        $this->actingAs($as);
        $d = json_decode((string) (new $tool)->handle(new Request($args))->content(), true);

        return is_array($d) ? $d : ['error' => true];
    }

    /** A page of this size in points, to within a point — the old engine and the designer round differently. */
    private function assertPageSize(string $pdf, float $w, float $h): void
    {
        $this->assertSame(1, preg_match('#/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)#', $pdf, $m), 'a page size');
        $this->assertEqualsWithDelta($w, (float) $m[1], 1.0);
        $this->assertEqualsWithDelta($h, (float) $m[2], 1.0);
    }

    public function test_a_badge_is_an_a6_page_and_a_certificate_is_still_a4(): void
    {
        $this->assertSame([105, 148], Formats::forType(EventBadges::TYPE)->page('portrait'));
        $this->assertSame('portrait', CertificateTemplate::make(['type' => EventBadges::TYPE])->pageOrientation());
        $this->assertSame('landscape', CertificateTemplate::make(['type' => 'participation'])->pageOrientation());
        $this->assertSame([297, 210], Layout::page('landscape'));   // the certificate, unchanged

        $badges = app(EventBadges::class);
        $pdf = $badges->render($this->officials[0]->fresh(), 'en');
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertPageSize($pdf, 297.6, 419.5);   // A6 in points (old engine or designer)

        // Arabic too, and the picture for a screen.
        $this->assertStringStartsWith('%PDF', $badges->render($this->officials[0]->fresh(), 'ar'));
        $this->assertStringStartsWith("\x89PNG", $badges->render($this->officials[0]->fresh(), 'en', 60));
    }

    public function test_the_organiser_sees_every_officials_badge_and_nobody_else_does(): void
    {
        $list = $this->actingAs($this->organiser->fresh())->getJson(route('me.badges.index', $this->key()))->assertOk()->json();
        $this->assertCount(5, $list['badges']);
        $this->assertSame('Official 1', $list['badges'][0]['name']);
        $this->assertSame('Referee 1', $list['badges'][0]['role']);
        $token = $list['badges'][0]['token'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $token);

        $this->actingAs($this->organiser->fresh())->get(route('me.badges.image', $token))->assertOk()->assertHeader('Content-Type', 'image/png');
        $pdf = $this->actingAs($this->organiser->fresh())->get(route('me.badges.pdf', $token).'?download=1');
        $pdf->assertOk();
        $this->assertStringStartsWith('attachment', $pdf->headers->get('Content-Disposition'));

        // A stranger, and one of the officials themselves: no list, no badge, by any token.
        foreach ([$this->createUser(), User::find($this->officials[1]->user_id)] as $who) {
            $this->actingAs($who->fresh())->getJson(route('me.badges.index', $this->key()))->assertNotFound();
            $this->actingAs($who->fresh())->getJson(route('me.badges.image', $token))->assertNotFound();
            $this->actingAs($who->fresh())->getJson(route('me.badges.pdf', $token))->assertNotFound();
            $this->actingAs($who->fresh())->postJson(route('me.certificates.bundle', ['context' => EventBadges::ALIAS, 'type' => EventBadges::TYPE] + $this->key()))->assertNotFound();
        }
        // An unknown token answers the same.
        $this->actingAs($this->organiser->fresh())->getJson(route('me.badges.image', str_repeat('a', 32)))->assertNotFound();
        $this->assertArrayHasKey('error', $this->mcp(EventBadgesTool::class, User::find($this->officials[1]->user_id), ['event' => $this->event->uuid]));
    }

    public function test_the_print_shop_file_lays_four_badges_to_an_a4_sheet(): void
    {
        $p = ['context' => EventBadges::ALIAS, 'type' => EventBadges::TYPE] + $this->key();
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.bundle.download', $p))->assertNotFound();

        // Built at once by the test queue; five badges is two A4 sheets.
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.bundle', $p))->assertOk()->assertJsonPath('state', 'ready');
        $pdf = $this->actingAs($this->organiser->fresh())->get(route('me.certificates.bundle.download', $p));
        $pdf->assertOk();
        $body = $pdf->streamedContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b(?!s)#', $body));
        $this->assertPageSize($body, 595.3, 841.9);   // A4

        // Appoint one more and the old file no longer answers for the officials as they are now.
        EventOfficial::create(['event_id' => $this->event->id, 'user_id' => $this->createUser()->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $this->organiser->id]);
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.bundle.download', $p))->assertNotFound();

        // The MCP reads the same file, and can build it.
        $this->assertSame('ready', $this->mcp(EventBadgesTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid, 'build' => true])['print_file']['state']);
    }

    public function test_the_qr_verifies_a_current_official_and_nothing_once_they_are_removed(): void
    {
        $badges = app(EventBadges::class);
        $official = $this->officials[2]->fresh();
        $url = $badges->verifyUrl($official);

        $page = $this->get($url);
        $page->assertOk();
        $page->assertSee('Official 3');
        $page->assertSee('Table');
        $page->assertSee('Spring Open');

        $official->delete();
        $gone = $this->get($url);
        $gone->assertNotFound();
        $never = $this->get(route('badges.verify', ['token' => str_repeat('Z', 32)]));
        $never->assertNotFound();
        // One answer for both: a stranger cannot tell a removed official from a code that never was.
        $this->assertSame(strip_tags($never->getContent()), strip_tags($gone->getContent()));
    }

    public function test_the_design_room_works_for_badges_and_keeps_a_badges_way_up(): void
    {
        $p = ['context' => EventBadges::ALIAS] + $this->key();
        $this->actingAs($this->organiser->fresh())->get(route('me.certificates.design', $p))->assertOk()->assertSee(__('cert::messages.badges_design'));
        $state = $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.design.state', $p))->assertOk()->json();
        $this->assertSame('badge', $state['templates'][0]['format']);
        $this->assertSame([105, 148], $state['formats']['badge']['page']['portrait']);
        $this->assertContains('role', collect($state['formats']['badge']['elements'])->pluck('key')->all());

        // A badge saves with no body text, and upright is its own way up (stored as no turn at all).
        $save = [
            'scope' => 'event', 'title' => ['en' => 'Staff', 'ar' => 'طاقم'], 'body' => ['en' => '', 'ar' => ''],
            'ornament_level' => 'full', 'background' => 'plain', 'orientation' => 'portrait',
            'layout_portrait' => ['role' => ['x' => 10, 'y' => 100, 'w' => 85, 'h' => 9], 'nonsense' => ['x' => 1]],
        ];
        $this->actingAs($this->organiser->fresh())->putJson(route('me.certificates.design.update', $p + ['type' => EventBadges::TYPE]), $save)->assertOk();
        $t = app(CertificateService::class)->templateFor(EventBadges::TYPE, $this->event);
        $this->assertNull($t->orientation);
        $this->assertSame('portrait', $t->pageOrientation());
        $this->assertSame(['role'], array_keys($t->layout_portrait));   // only the badge's own elements are kept
        $this->assertSame('Staff', $t->title('en'));

        // Turned flat, it is stored as turned.
        $this->actingAs($this->organiser->fresh())->putJson(route('me.certificates.design.update', $p + ['type' => EventBadges::TYPE]), ['orientation' => 'landscape'] + $save)->assertOk();
        $this->assertSame('landscape', $t->fresh()->orientation);

        // The preview is the badge, filled with a real official.
        $this->actingAs($this->organiser->fresh())->get(route('me.certificates.design.preview', $p + ['type' => EventBadges::TYPE]))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Certificates of the same event keep their own design, untouched.
        $cert = app(CertificateService::class)->templateFor('participation', $this->event);
        $this->assertSame('landscape', $cert->pageOrientation());
        $this->assertNotSame($t->id, $cert->id);

        // A stranger may not open the badge design room.
        $this->actingAs($this->createUser())->getJson(route('me.certificates.design.state', $p))->assertNotFound();
    }
}

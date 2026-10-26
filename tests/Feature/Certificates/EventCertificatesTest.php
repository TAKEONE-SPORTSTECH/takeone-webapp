<?php

namespace Tests\Feature\Certificates;

use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Models\Certificate;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventCertificateSetting;
use App\Events\Support\EventCertificates;
use App\Mcp\Tools\ApproveEventCertificatesTool;
use App\Mcp\Tools\DeleteEventCertificateDraftsTool;
use App\Mcp\Tools\EventCertificateBundleTool;
use App\Mcp\Tools\GenerateEventCertificatesTool;
use App\Mcp\Tools\ListEventCertificatesTool;
use App\Mcp\Tools\ListMyCertificatesTool;
use App\Mcp\Tools\RevokeCertificateTool;
use App\Members\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Event certificates (App\Certificates + App\Events\Support\EventCertificates).
 *
 * What this protects: only a FINISHED division earns anything; everyone who
 * fought gets participation and the podium gets its medals; drafting twice
 * makes nothing twice; nothing reaches a holder until approved; nobody reads
 * or acts on another event's or person's certificates by changing an id; the
 * host's shared design needs the host's own people; uploads are checked by
 * their bytes; and with the switch off none of it exists.
 */
class EventCertificatesTest extends TestCase
{
    private User $organiser;

    private Tenant $club;

    private ClubEvent $event;

    /** @var array<string, User> */
    private array $athletes = [];

    /** @var array<string, ClubEventRegistration> */
    private array $entries = [];

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
            'date' => now()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
            'location' => 'Isa Sports City',
        ]);
        // Drafting by hand in most tests; the automatic path has its own test.
        EventCertificateSetting::create(['event_id' => $this->event->id, 'trigger' => 'manual']);
    }

    /** Four who fought — A beats B, C beats D, A beats C in the final — and E, entered but never on the mat. */
    private function decidedDivision(bool $decided = true): EventCategory
    {
        $cat = EventCategory::create(['event_id' => $this->event->id, 'name' => 'Kumite -67kg', 'sort_order' => 1]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $k) {
            $this->athletes[$k] = $this->createUser(['full_name' => 'Athlete '.strtoupper($k)]);
            $this->entries[$k] = ClubEventRegistration::create([
                'event_id' => $this->event->id, 'user_id' => $this->athletes[$k]->id, 'status' => 'joined',
                'role' => 'participant', 'category_id' => $cat->id, 'entry_state' => 'complete',
            ]);
        }
        $id = fn ($k) => $this->entries[$k]->id;
        DB::table('event_matches')->insert([
            ['event_id' => $this->event->id, 'category_id' => $cat->id, 'round' => 'Semifinal', 'slot' => 1, 'a_competitor_id' => $id('a'), 'b_competitor_id' => $id('b'), 'winner' => 'a', 'status' => 'completed'],
            ['event_id' => $this->event->id, 'category_id' => $cat->id, 'round' => 'Semifinal', 'slot' => 2, 'a_competitor_id' => $id('c'), 'b_competitor_id' => $id('d'), 'winner' => 'a', 'status' => 'completed'],
            ['event_id' => $this->event->id, 'category_id' => $cat->id, 'round' => 'Final', 'slot' => 1, 'a_competitor_id' => $id('a'), 'b_competitor_id' => $id('c'), 'winner' => $decided ? 'a' : null, 'status' => $decided ? 'completed' : 'upcoming'],
        ]);
        // "e" entered but never stepped on the mat: no participation.
        if ($decided) {
            $cat->update(['podium' => [
                ['place' => 1, 'name' => 'Athlete A', 'competitor_id' => $id('a')],
                ['place' => 2, 'name' => 'Athlete C', 'competitor_id' => $id('c')],
                ['place' => 3, 'name' => 'Athlete B', 'competitor_id' => $id('b')],
                ['place' => 3, 'name' => 'Athlete D', 'competitor_id' => $id('d')],
            ]]);
        }

        return $cat;
    }

    private function generate(): array
    {
        return $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.generate', ['context' => 'club_event', 'key' => $this->event->uuid]))
            ->assertOk()->json();
    }

    private function mcp(string $tool, User $as, array $args): array
    {
        $this->actingAs($as);
        $d = json_decode((string) (new $tool)->handle(new Request($args))->content(), true);

        return is_array($d) ? $d : ['error' => true];
    }

    /* ===================== drafting ===================== */

    public function test_a_finished_division_drafts_participation_for_who_fought_and_the_podium(): void
    {
        $this->decidedDivision();
        $d = $this->generate();

        $this->assertSame(8, $d['created']);   // 4 participation (not e) + 4 medals
        $this->assertSame(4, Certificate::where('type', 'participation')->count());
        $this->assertSame(4, Certificate::where('type', 'podium')->count());
        $this->assertSame(0, Certificate::where('recipient_id', $this->athletes['e']->id)->count());
        $gold = Certificate::where('type', 'podium')->where('recipient_id', $this->athletes['a']->id)->firstOrFail();
        $this->assertSame(1, $gold->details['place']);
        $this->assertTrue(Certificate::all()->every(fn ($c) => $c->isDraft()));

        // Idempotent.
        $this->assertSame(0, $this->generate()['created']);
        $this->assertSame(8, Certificate::count());
    }

    public function test_storing_a_podium_drafts_on_its_own_when_the_organiser_chose_so(): void
    {
        EventCertificateSetting::where('event_id', $this->event->id)->update(['trigger' => 'division']);
        $this->decidedDivision();   // stores the podium → observer → job (sync in tests)

        $this->assertSame(8, Certificate::count());
        $this->assertTrue(Certificate::all()->every(fn ($c) => $c->isDraft()));
    }

    public function test_a_division_still_running_drafts_nothing(): void
    {
        $this->decidedDivision(decided: false);
        $d = $this->generate();

        $this->assertSame(0, $d['created']);
        $this->assertSame(1, $d['eligible']['pending']);
        $this->assertSame(0, Certificate::count());
    }

    public function test_a_podium_draft_follows_a_corrected_result_until_approved(): void
    {
        $cat = $this->decidedDivision();
        $this->generate();
        $id = fn ($k) => $this->entries[$k]->id;

        // The table corrects the final: C won.
        $cat->update(['podium' => [
            ['place' => 1, 'name' => 'Athlete C', 'competitor_id' => $id('c')],
            ['place' => 2, 'name' => 'Athlete A', 'competitor_id' => $id('a')],
            ['place' => 3, 'name' => 'Athlete B', 'competitor_id' => $id('b')],
        ]]);
        $this->generate();

        $podium = Certificate::where('type', 'podium')->get()->mapWithKeys(fn ($c) => [$c->recipient_id => $c->details['place']]);
        $this->assertSame(1, $podium[$this->athletes['c']->id]);
        $this->assertSame(2, $podium[$this->athletes['a']->id]);
        $this->assertArrayNotHasKey($this->athletes['d']->id, $podium->all());   // off the podium: draft gone
    }

    /* ===================== approval and the holder ===================== */

    public function test_approving_stores_the_pdf_on_first_open_and_the_holder_sees_only_approved_ones(): void
    {
        $this->decidedDivision();
        $this->generate();
        $holder = $this->athletes['a'];

        // A draft is not the holder's to see.
        $draft = Certificate::where('recipient_id', $holder->id)->where('type', 'podium')->firstOrFail();
        $this->actingAs($holder->fresh())->getJson(route('me.certificates.pdf', $draft->uuid))->assertNotFound();
        $this->actingAs($holder->fresh())->getJson(route('me.certificates.list'))->assertOk()->assertJsonCount(0, 'certificates');

        $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.approve', ['context' => 'club_event', 'key' => $this->event->uuid]), ['uuids' => [$draft->uuid]])
            ->assertOk()->assertJsonPath('approved', 1);

        $draft->refresh();
        $this->assertTrue($draft->isApproved());
        // No file until somebody opens it: an event's worth of PDFs nobody reads is not drawn.
        $stored = 'events/'.$this->event->uuid.'/certificates/'.$draft->uuid.'.pdf';
        Storage::disk('local')->assertMissing($stored);

        $this->actingAs($holder->fresh())->getJson(route('me.certificates.list'))->assertOk()->assertJsonCount(1, 'certificates')
            ->assertJsonMissingPath('certificates.0.holder');
        $pdf = $this->actingAs($holder->fresh())->get(route('me.certificates.pdf', $draft->uuid));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        Storage::disk('local')->assertExists($stored);   // drawn once, kept from then on
        $this->assertSame($pdf->getContent(), $this->actingAs($holder->fresh())->get(route('me.certificates.pdf', $draft->uuid))->getContent());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $holder->id, 'type' => 'certificate']);
    }

    public function test_every_draft_can_be_deleted_in_one_go_and_nobody_else_can(): void
    {
        $this->decidedDivision();
        $this->generate();
        $issued = Certificate::where('recipient_id', $this->athletes['a']->id)->where('type', 'podium')->firstOrFail();
        app(CertificateService::class)->approve($issued, $this->organiser);
        $url = route('me.certificates.drafts.destroy', ['context' => 'club_event', 'key' => $this->event->uuid]);

        // Not the organiser: refused, and nothing goes.
        foreach ([$this->createUser(), $this->athletes['b']] as $who) {
            $this->actingAs($who->fresh())->deleteJson($url, ['all' => true])->assertNotFound();
        }
        $this->assertArrayHasKey('error', $this->mcp(DeleteEventCertificateDraftsTool::class, $this->athletes['b']->fresh(), ['event' => $this->event->uuid]));
        $this->assertSame(7, Certificate::where('status', 'draft')->count());

        // Asked for nothing in particular: refused rather than taken as "all".
        $this->actingAs($this->organiser->fresh())->deleteJson($url, [])->assertStatus(422);

        $d = $this->actingAs($this->organiser->fresh())->deleteJson($url, ['all' => true])->assertOk()->json();
        $this->assertSame(7, $d['deleted']);
        $this->assertSame(0, $d['remaining']);
        $this->assertSame(0, $d['counts']['draft']);
        $this->assertSame(0, Certificate::where('status', 'draft')->count());
        $this->assertTrue($issued->fresh()->isApproved());   // an issued one is never touched

        // The MCP does the same.
        $this->generate();
        $this->assertSame(0, $this->mcp(DeleteEventCertificateDraftsTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid])['remaining']);
        $this->assertTrue($issued->fresh()->isApproved());
    }

    public function test_approve_all_goes_in_batches(): void
    {
        $this->decidedDivision();
        $this->generate();

        $d = $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.approve', ['context' => 'club_event', 'key' => $this->event->uuid]), ['all' => true])
            ->assertOk()->json();

        $this->assertSame(8, $d['approved']);
        $this->assertSame(0, $d['remaining']);
    }

    /* ===================== nobody else ===================== */

    public function test_another_person_cannot_reach_a_certificate_or_an_event_by_changing_the_id(): void
    {
        $this->decidedDivision();
        $this->generate();
        $c = Certificate::where('recipient_id', $this->athletes['a']->id)->where('type', 'podium')->firstOrFail();
        app(CertificateService::class)->approve($c, $this->organiser);

        $stranger = $this->createUser();
        $member = $this->athletes['b'];   // a competitor at the same event
        foreach ([$stranger, $member] as $who) {
            // (A browser GET to a missing page is sent home by the app's handler; JSON sees the 404.)
            $this->actingAs($who->fresh())->getJson(route('me.certificates.pdf', $c->uuid))->assertNotFound();
            $this->actingAs($who->fresh())->getJson(route('me.certificates.image', $c->uuid))->assertNotFound();
            $this->assertStringNotContainsString('%PDF', (string) $this->actingAs($who->fresh())->get(route('me.certificates.pdf', $c->uuid))->getContent());
            $this->actingAs($who->fresh())->getJson(route('me.certificates.manage', ['context' => 'club_event', 'key' => $this->event->uuid]))->assertNotFound();
            $this->actingAs($who->fresh())->postJson(route('me.certificates.generate', ['context' => 'club_event', 'key' => $this->event->uuid]))->assertNotFound();
            $this->actingAs($who->fresh())->postJson(route('me.certificates.revoke', $c->uuid), ['reason' => 'nope nope'])->assertNotFound();
        }
        // An unknown uuid answers the same as somebody else's.
        $this->actingAs($stranger->fresh())->getJson(route('me.certificates.pdf', '11111111-2222-3333-4444-555555555555'))->assertNotFound();
        $this->assertTrue($c->fresh()->isApproved());
    }

    /* ===================== verification ===================== */

    public function test_the_qr_page_knows_approved_and_revoked_and_never_a_draft(): void
    {
        $this->decidedDivision();
        $this->generate();
        $c = Certificate::where('recipient_id', $this->athletes['a']->id)->where('type', 'podium')->firstOrFail();

        $this->get(route('certificates.verify', $c->verify_token))->assertNotFound();   // still a draft

        app(CertificateService::class)->approve($c, $this->organiser);
        $this->get(route('certificates.verify', $c->verify_token))->assertOk()->assertSee('Athlete A')->assertSee($c->certificate_no);

        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.revoke', $c->uuid), ['reason' => ''])->assertStatus(422);
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.revoke', $c->uuid), ['reason' => 'Result overturned'])->assertOk();
        auth()->logout();
        $this->get(route('certificates.verify', $c->verify_token))->assertOk()->assertSee(__('cert::messages.verify_revoked_title'));
        $this->get('/verify/'.str_repeat('a', 32))->assertNotFound();
    }

    /* ===================== the design room ===================== */

    public function test_the_host_default_needs_the_host_and_the_event_design_needs_the_event(): void
    {
        // Somebody who created the event but does not run its club.
        $creator = $this->createUser();
        $this->event->update(['created_by' => $creator->id]);
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $body = [
            'title' => ['en' => 'Well done', 'ar' => 'أحسنت'], 'body' => ['en' => 'for {event}', 'ar' => 'في {event}'],
            'ornament_level' => 'full', 'background' => 'guilloche', 'show_photo' => false,
        ];

        $this->actingAs($creator->fresh())->putJson(route('me.certificates.design.update', $p), $body + ['scope' => 'host'])->assertForbidden();
        $this->actingAs($creator->fresh())->putJson(route('me.certificates.design.update', $p), $body + ['scope' => 'event'])->assertOk()
            ->assertJsonPath('templates.0.scope', 'event')->assertJsonPath('templates.0.background', 'guilloche');

        // The club owner may set the club's default; the event keeps its own until it chooses the default.
        $this->actingAs($this->organiser->fresh())->putJson(route('me.certificates.design.update', $p), array_merge($body, ['scope' => 'host', 'title' => ['en' => 'Club default']]))->assertOk()
            ->assertJsonPath('templates.0.scope', 'host');
        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $this->assertSame('club', $t->owner_type);
        $this->assertSame('Club default', $t->title['en']);

        // The preview is a picture, drawn by the same engine — and it says where each
        // element's ink landed, so the editor's handles hug the words.
        $png = $this->actingAs($this->organiser->fresh())->get(route('me.certificates.design.preview', $p).'?locale=ar');
        $png->assertOk();
        $this->assertSame("\xFF\xD8\xFF", substr($png->getContent(), 0, 3));   // a JPEG: previews are light
        $drawn = json_decode($png->headers->get('X-Cert-Drawn'), true);
        $this->assertArrayHasKey('name', $drawn);
        $this->assertLessThan(200, $drawn['name']['w']);      // a short sample name, not the 249 mm box
        $this->assertGreaterThan(5, $drawn['name']['w']);
    }

    public function test_a_rearranged_layout_is_kept_clean_and_drawn(): void
    {
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $body = [
            'scope' => 'host', 'title' => ['en' => 'Well done'], 'body' => ['en' => 'for {event}'],
            'ornament_level' => 'full', 'background' => 'plain', 'show_photo' => false,
            'layout' => [
                'name' => ['x' => 30, 'y' => 60, 'w' => 150, 'h' => 20, 'scale' => 9, 'color' => '#B8892B', 'align' => 'start', 'evil' => '<script>'],
                'panel' => ['hidden' => false, 'opacity' => 5, 'color' => 'red'],
                'nonsense' => ['x' => 1],
            ],
        ];

        $this->actingAs($this->organiser->fresh())->putJson(route('me.certificates.design.update', $p), $body)->assertOk();
        $layout = app(CertificateService::class)->templateFor('participation', $this->event)->layout;

        $this->assertSame(['name', 'panel'], array_keys($layout));
        $this->assertSame(3.0, (float) $layout['name']['scale']);          // clamped
        $this->assertSame('#b8892b', $layout['name']['color']);
        $this->assertArrayNotHasKey('evil', $layout['name']);
        $this->assertNull($layout['panel']['color']);                        // not a colour
        $this->assertSame(1.0, (float) $layout['panel']['opacity']);

        // The same arrangement previews before it is saved, and draws.
        $png = $this->actingAs($this->organiser->fresh())->post(route('me.certificates.design.preview', $p).'?locale=en', $body);
        $png->assertOk();
        $this->assertSame("\xFF\xD8\xFF", substr($png->getContent(), 0, 3));   // a JPEG: previews are light

        // A certificate drafted after it carries it into its PDF.
        $this->decidedDivision();
        $this->generate();
        $c = Certificate::where('type', 'participation')->firstOrFail();
        $this->assertStringStartsWith('%PDF', app(CertificateService::class)->pdfContents($c));
    }

    public function test_the_page_can_stand_upright_and_each_way_keeps_its_own_arrangement(): void
    {
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $body = [
            'scope' => 'host', 'title' => ['en' => 'Well done'], 'body' => ['en' => 'for {event}'],
            'ornament_level' => 'full', 'background' => 'guilloche', 'show_photo' => false,
            'layout' => ['name' => ['x' => 30, 'y' => 60]],
            'orientation' => 'portrait',
            'layout_portrait' => ['name' => ['x' => 20, 'y' => 280, 'w' => 999], 'nonsense' => ['x' => 1]],
        ];
        $as = $this->actingAs($this->organiser->fresh());
        $as->putJson(route('me.certificates.design.update', $p), $body)->assertOk()->assertJsonPath('templates.0.orientation', 'portrait');

        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $this->assertSame('portrait', $t->orientation);
        $this->assertSame(['x' => 30.0, 'y' => 60.0], array_map('floatval', $t->layout['name']));      // landscape kept
        $this->assertSame(['name'], array_keys($t->layout_portrait));
        $this->assertSame(210.0, (float) $t->layout_portrait['name']['w']);                            // held to the portrait page
        $this->assertSame(280.0, (float) $t->layout_portrait['name']['y']);                            // a y a landscape page has no room for

        // The preview and the stored PDF are both upright.
        $jpg = $as->post(route('me.certificates.design.preview', $p).'?locale=ar', $body)->assertOk()->getContent();
        [$w, $h] = getimagesizefromstring($jpg);
        $this->assertGreaterThan($w, $h);
        $this->decidedDivision();
        $this->generate();
        $pdf = app(CertificateService::class)->pdfContents(Certificate::where('type', 'participation')->firstOrFail());
        $this->assertMatchesRegularExpression('#/MediaBox \[\s*0\.?0*\s+0\.?0*\s+595\.\d+\s+841\.\d+#', $pdf);

        // A caller that never mentions the page leaves it alone; turning it back is landscape again.
        unset($body['orientation'], $body['layout_portrait']);
        $as->putJson(route('me.certificates.design.update', $p), $body)->assertOk();
        $this->assertSame('portrait', $t->fresh()->orientation);
        $as->putJson(route('me.certificates.design.update', $p), $body + ['orientation' => 'landscape'])->assertOk();
        $this->assertNull($t->fresh()->orientation);
        $this->assertNotNull($t->fresh()->layout_portrait);                                           // the upright arrangement waits
        $as->putJson(route('me.certificates.design.update', $p), $body + ['orientation' => 'sideways'])->assertStatus(422);
    }

    public function test_every_element_can_be_added_and_removed_and_texts_are_the_organisers_own(): void
    {
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $body = [
            'scope' => 'host', 'title' => ['en' => 'Well done'], 'body' => ['en' => 'for {event}'],
            'ornament_level' => 'off', 'background' => 'paper', 'show_photo' => false, 'print_bare' => true,
            'elements' => [
                ['key' => 'text_abc123', 'text' => ['en' => '<b>Awarded</b> to {name}', 'ar' => 'مقدمة'], 'style' => 'heading', 'evil' => 1],
                ['key' => '../../etc', 'text' => ['en' => 'x']],
                ['key' => 'text_abc123', 'text' => ['en' => 'duplicate']],
            ],
            'layout' => ['text_abc123' => ['x' => 40, 'y' => 150], 'seal' => ['hidden' => true]],
        ];
        $as = $this->actingAs($this->organiser->fresh());
        $as->putJson(route('me.certificates.design.update', $p), $body)->assertOk()
            ->assertJsonPath('templates.0.elements.0.key', 'text_abc123')
            ->assertJsonPath('templates.0.print_bare', true);

        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $this->assertCount(1, $t->elements);                                      // the bad key and the duplicate are dropped
        $this->assertSame('Awarded to {name}', $t->elements[0]['text']['en']);    // markup stripped
        $this->assertSame(['key', 'text', 'style'], array_keys($t->elements[0]));
        $this->assertSame('paper', $t->background);
        $this->assertTrue($t->layout['seal']['hidden']);

        // The text is drawn where it was put, with its placeholders filled.
        $sample = Certificate::make(['type' => 'participation', 'issued_on' => today()]);
        $this->assertSame('Awarded to Sara', app(CertificateService::class)->extras($t, $sample, 'en', 'Sara')['text_abc123']['text']);
        $as->post(route('me.certificates.design.preview', $p).'?locale=en', $body)->assertOk();
        $drawn = json_decode($as->post(route('me.certificates.design.preview', $p).'?locale=en', $body)->headers->get('X-Cert-Drawn'), true);
        $this->assertSame(150.0, (float) $drawn['text_abc123']['y']);
        $this->assertArrayNotHasKey('seal', $drawn);                              // removed from the page

        // Print on my own paper: the print copy is bare, the download is not.
        $this->assertSame('blank', $t->backgroundFor('print'));
        $this->assertSame('paper', $t->backgroundFor('download'));

        // Deleting the text deletes it; a caller that never mentions elements leaves them alone.
        unset($body['elements']);
        $as->putJson(route('me.certificates.design.update', $p), $body)->assertOk();
        $this->assertCount(1, $t->fresh()->elements);
        $as->putJson(route('me.certificates.design.update', $p), $body + ['elements' => []])->assertOk();
        $this->assertNull($t->fresh()->elements);
        $as->putJson(route('me.certificates.design.update', $p), $body + ['artwork_fit' => 'zoom'])->assertStatus(422);
    }

    public function test_artwork_keeps_its_original_and_fits_the_page_the_way_asked(): void
    {
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $as = $this->actingAs($this->organiser->fresh());
        $as->post(route('me.certificates.design.artwork.store', $p), ['image' => UploadedFile::fake()->image('art.jpg', 1200, 1700)], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('templates.0.artwork_fit', 'fit');                // tall on a landscape page: shown whole
        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $files = app(\Ahl\Certificates\Contracts\FileStore::class);
        $this->assertNotNull($src = $files->get($t, 'background_source'));
        $this->assertSame([1200, 1700], array_slice(getimagesizefromstring($src['contents']), 0, 2));

        $body = ['scope' => 'host', 'title' => ['en' => 'A'], 'body' => ['en' => 'B'], 'ornament_level' => 'full', 'background' => 'artwork', 'show_photo' => false];
        foreach (['fill', 'fit', 'stretch', 'tile', 'center'] as $fit) {
            $as->post(route('me.certificates.design.preview', $p).'?locale=en', $body + ['artwork_fit' => $fit])->assertOk();
        }
        $as->deleteJson(route('me.certificates.design.artwork.store', $p))->assertOk();
        $this->assertNull($files->get($t, 'background_source'));                 // the original goes with it
        $this->assertNull($t->fresh()->artwork_fit);
    }

    public function test_anything_printed_in_the_picture_can_be_erased(): void
    {
        // Paints out: a black block on white comes back the colour around it, the rest untouched.
        $im = imagecreatetruecolor(297, 210);
        imagefill($im, 0, 0, imagecolorallocate($im, 250, 250, 250));
        imagefilledrectangle($im, 100, 80, 160, 110, imagecolorallocate($im, 0, 0, 0));
        ob_start(); imagepng($im); $png = (string) ob_get_clean();
        $out = \Ahl\Certificates\Support\Eraser::apply(['contents' => $png, 'mime' => 'image/png'], [['x' => 95, 'y' => 75, 'w' => 72, 'h' => 42]], 297, 210);
        $back = imagecreatefromstring($out['contents']);
        $this->assertGreaterThan(235, (imagecolorat($back, 130, 95) >> 16) & 255);   // the block is gone
        $this->assertGreaterThan(235, (imagecolorat($back, 20, 20) >> 16) & 255);    // the page is as it was

        // Stored as the organiser's own element, for the way up it was drawn on only.
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $body = [
            'scope' => 'host', 'title' => ['en' => 'A'], 'body' => ['en' => 'B'], 'ornament_level' => 'full', 'background' => 'plain', 'show_photo' => false,
            'elements' => [['key' => 'erase_abc123', 'kind' => 'erase', 'page' => 'landscape', 'evil' => 1], ['key' => 'erase_zzz999', 'kind' => 'erase', 'page' => ['x']]],
            'layout' => ['erase_abc123' => ['x' => 40, 'y' => 30, 'w' => 80, 'h' => 20]],
        ];
        $as = $this->actingAs($this->organiser->fresh());
        $as->putJson(route('me.certificates.design.update', $p), $body)->assertOk();
        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $this->assertSame([['key' => 'erase_abc123', 'kind' => 'erase', 'page' => 'landscape'], ['key' => 'erase_zzz999', 'kind' => 'erase', 'page' => 'landscape']], $t->elements);
        $this->assertSame(['erase_abc123', 'erase_zzz999'], $t->customKeys('landscape'));
        $this->assertSame([], $t->customKeys('portrait'));
        $this->assertSame(40.0, (float) \Ahl\Certificates\Support\Layout::resolve($t->layout, false, true, 'landscape', $t->customKeys())['erase_abc123']['x']);   // never mirrored
        $as->post(route('me.certificates.design.preview', $p).'?locale=en', $body)->assertOk();
        $as->post(route('me.certificates.design.preview', $p).'?locale=ar', $body)->assertOk();
    }

    public function test_automatic_ink_stays_readable_on_any_background(): void
    {
        $contrast = \Ahl\Certificates\Support\Contrast::class;
        $this->assertSame('#3b3548', $contrast::ink('#3b3548', '#fbf8f2'));       // reads on paper: kept
        $this->assertSame('#ffffff', $contrast::ink('#3b3548', '#14213d'));       // on navy: white
        $this->assertSame('#1f1a2e', $contrast::ink('#ffffff', '#f5efe0'));       // white on cream: dark
        // On a mid-grey neither ink reaches 4.5:1 (that is what the text panel is for) — the
        // better of the two is still chosen.
        $grey = '#7a7f85';
        $chosen = $contrast::ink('#8a939c', $grey);
        $this->assertSame(max($contrast::ratio('#1f1a2e', $grey), $contrast::ratio('#ffffff', $grey)), $contrast::ratio($chosen, $grey));
    }

    public function test_design_uploads_are_checked_by_their_bytes(): void
    {
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $as = $this->actingAs($this->organiser->fresh());

        $php = UploadedFile::fake()->createWithContent('sig.png', '<?php echo 1;');
        $as->post(route('me.certificates.design.signature.store', $p + ['slot' => 1]), ['image' => $php], ['Accept' => 'application/json'])->assertStatus(422);
        $svg = UploadedFile::fake()->createWithContent('sig.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $as->post(route('me.certificates.design.signature.store', $p + ['slot' => 1]), ['image' => $svg], ['Accept' => 'application/json'])->assertStatus(422);
        $fakeArt = UploadedFile::fake()->createWithContent('art.jpg', '<?php echo 1;');
        $as->post(route('me.certificates.design.artwork.store', $p), ['image' => $fakeArt], ['Accept' => 'application/json'])->assertStatus(422);

        // Any real picture is taken and fitted to the page — small ones with a warning, tall ones whole.
        foreach ([[800, 600], [900, 1600], [4000, 1000]] as [$w, $h]) {
            $r = $as->post(route('me.certificates.design.artwork.store', $p), ['image' => UploadedFile::fake()->image("art-{$w}.jpg", $w, $h)], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('templates.0.has_artwork', true);
            $this->assertSame(max($w, $h) < 1200 ? __('cert::messages.artwork_saved_small') : __('cert::messages.artwork_saved'), $r->json('message'));
        }
        $art = collect(Storage::disk('local')->allFiles())->first(fn ($f) => str_ends_with($f, '/background'));
        $this->assertSame([3508, 2480], array_slice(getimagesizefromstring(Storage::disk('local')->get($art)), 0, 2));

        $sig = UploadedFile::fake()->image('signature.png', 600, 200);
        $as->post(route('me.certificates.design.signature.store', $p + ['slot' => 1]), ['image' => $sig], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('templates.0.has_signature.1', true);
        $files = collect(Storage::disk('local')->allFiles())->filter(fn ($f) => str_contains($f, '/certificates/templates/') && str_ends_with($f, 'signature_1'));
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('signature.png', $files->first());   // the server names it
        $this->assertStringStartsWith('clubs/', $files->first());                // the owner's own folder
    }

    /* ===================== the switch ===================== */

    public function test_with_the_switch_off_nothing_exists(): void
    {
        config(['certificates.enabled' => false]);
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.list'))->assertNotFound();
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.manage', ['context' => 'club_event', 'key' => $this->event->uuid]))->assertNotFound();
        auth()->logout();
        $this->get('/verify/'.str_repeat('a', 32))->assertNotFound();
    }

    public function test_my_certificates_page_renders_on_both_devices(): void
    {
        $this->actingAs($this->organiser->fresh())->get(route('me.certificates.index'))->assertOk()->assertSee(__('cert::messages.mine_title'));
        $this->actingAs($this->organiser->fresh())->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Mobile Safari/537.36')
            ->get(route('me.certificates.index'))->assertOk()->assertSee(__('cert::messages.mine_title'));
    }

    public function test_the_design_room_renders_for_the_organiser(): void
    {
        $this->actingAs($this->organiser->fresh())
            ->get(route('me.certificates.design', ['context' => 'club_event', 'key' => $this->event->uuid]))
            ->assertOk()->assertSee(__('cert::messages.design_title'));
    }

    public function test_issued_certificates_download_one_by_one_and_as_one_pdf_for_the_print_shop(): void
    {
        $this->decidedDivision();
        $this->generate();
        $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.approve', ['context' => 'club_event', 'key' => $this->event->uuid]), ['all' => true])->assertOk();
        $p = ['context' => 'club_event', 'key' => $this->event->uuid];
        $issued = Certificate::where('status', 'approved')->where('type', 'participation')->count();
        $this->assertGreaterThan(1, $issued);

        // One at a time: a real PDF, as a download.
        $one = Certificate::where('status', 'approved')->firstOrFail();
        $r = $this->actingAs($this->organiser->fresh())->get(route('me.certificates.pdf', $one->uuid).'?download=1');
        $r->assertOk();
        $this->assertStringStartsWith('attachment', $r->headers->get('Content-Disposition'));

        // Nobody else can ask for the bundle or fetch it.
        foreach ([$this->createUser(), $this->athletes['b']] as $who) {
            $this->actingAs($who->fresh())->postJson(route('me.certificates.bundle', $p + ['type' => 'participation']))->assertNotFound();
            $this->actingAs($who->fresh())->getJson(route('me.certificates.bundle.download', $p + ['type' => 'participation']))->assertNotFound();
        }
        // A design the context does not issue is not a door either.
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.bundle', $p + ['type' => 'nonsense']))->assertNotFound();

        // The console offers one PDF per design; nothing is built until asked.
        $state = $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.manage', $p))->assertOk()->json('bundles');
        $this->assertSame('idle', collect($state)->firstWhere('type', 'participation')['state']);
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.bundle.download', $p + ['type' => 'participation']))->assertNotFound();

        // Asked: built (the test queue runs it at once), then the same file answers.
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.bundle', $p + ['type' => 'participation']))
            ->assertOk()->assertJsonPath('state', 'ready');
        $pdf = $this->actingAs($this->organiser->fresh())->get(route('me.certificates.bundle.download', $p + ['type' => 'participation']));
        $pdf->assertOk();
        $body = $pdf->streamedContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertSame($issued, preg_match_all('#/Type\s*/Page\b(?!s)#', $body));
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));

        // The MCP sees the same file, and a stranger sees nothing.
        $this->assertSame('ready', $this->mcp(EventCertificateBundleTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid, 'type' => 'participation'])['state']);
        $this->assertArrayHasKey('error', $this->mcp(EventCertificateBundleTool::class, $this->athletes['b']->fresh(), ['event' => $this->event->uuid, 'type' => 'participation']));

        // Revoke one and the old file no longer answers for what is issued now.
        app(CertificateService::class)->revoke(Certificate::where('status', 'approved')->where('type', 'participation')->first(), $this->organiser, 'Result overturned');
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.bundle.download', $p + ['type' => 'participation']))->assertNotFound();
    }

    /**
     * The reported bug (2026-10-26): nationalities corrected after the certificates were
     * issued, issued again, downloaded — and the file still flew the old flags, because
     * the stored PDFs were reused. A corrected person or club must reach the next download.
     */
    public function test_correcting_a_person_or_their_club_reaches_the_next_download(): void
    {
        $this->decidedDivision();
        $this->generate();
        $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.approve', ['context' => 'club_event', 'key' => $this->event->uuid]), ['all' => true])->assertOk();
        $p = ['context' => 'club_event', 'key' => $this->event->uuid, 'type' => 'participation'];
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.bundle', $p))->assertOk()->assertJsonPath('state', 'ready');
        $this->actingAs($this->organiser->fresh())->get(route('me.certificates.bundle.download', $p))->assertOk();

        $service = app(CertificateService::class);
        $mine = Certificate::where('status', 'approved')->where('type', 'participation')->where('recipient_id', $this->athletes['a']->id)->firstOrFail();
        $before = $service->pageStamp($mine);
        $this->assertSame($before, $service->pageStamp($mine->fresh()), 'nothing changed, nothing is redrawn');

        // The nationality (the flag on the page) is corrected: the print-shop file is stale,
        // and the stored copy of the certificate is drawn again.
        $this->athletes['a']->forceFill(['nationality' => 'IN'])->save();
        $this->actingAs($this->organiser->fresh())->getJson(route('me.certificates.bundle.download', $p))->assertNotFound();
        $this->assertNotSame($before, $service->pageStamp($mine->fresh()));

        // The name too.
        $this->athletes['a']->forceFill(['full_name' => 'Athlete A Corrected'])->save();
        $r = $this->actingAs($this->organiser->fresh())->get(route('me.certificates.pdf', $mine->uuid).'?download=1')->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'cert');
        file_put_contents($file, $r->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $r->streamedContent() : $r->getContent());
        try {
            $text = (string) shell_exec('pdftotext '.escapeshellarg($file).' -');
        } finally {
            @unlink($file);
        }
        $this->assertStringContainsString('Athlete A Corrected', $text);

        // The club they competed for is corrected on the entry: issuing again writes it on.
        $this->entries['a']->forceFill(['representing_tenant_id' => $this->club->id])->save();
        $this->generate();
        $this->assertSame($this->club->club_name, $mine->fresh()->details['club'] ?? null);
        $this->assertSame('approved', $mine->fresh()->status->value, 'issuing again never re-drafts an approved certificate');

        // A new photograph reaches it too, when issued again; the age it was issued with stays.
        $age = $mine->fresh()->details['snapshot']['age'] ?? null;
        $this->athletes['a']->forceFill(['profile_picture' => 'members/'.$this->athletes['a']->uuid.'/profile/new-face.jpg'])->save();
        $this->generate();
        $this->assertSame('members/'.$this->athletes['a']->uuid.'/profile/new-face.jpg', $mine->fresh()->details['snapshot']['photo']['path'] ?? null);
        $this->assertSame($age, $mine->fresh()->details['snapshot']['age'] ?? null);

        // Rebuilt, the file answers again.
        $this->actingAs($this->organiser->fresh())->postJson(route('me.certificates.bundle', $p))->assertOk()->assertJsonPath('state', 'ready');
        $this->actingAs($this->organiser->fresh())->get(route('me.certificates.bundle.download', $p))->assertOk();
    }

    public function test_a_print_run_draws_many_certificates_to_a_chromium_and_keeps_their_order(): void
    {
        config(['printables.enabled' => true, 'printables.batch' => ['enabled' => true, 'per_chromium' => 1, 'parallel' => 2]]);
        $this->decidedDivision();
        $this->generate();
        $this->actingAs($this->organiser->fresh())
            ->postJson(route('me.certificates.approve', ['context' => 'club_event', 'key' => $this->event->uuid]), ['all' => true])->assertOk();
        $issued = Certificate::where('status', 'approved')->where('type', 'participation')->orderBy('id')->with('recipient')->get();
        $this->assertGreaterThan(1, $issued->count());

        // One certificate per Chromium, two at a time: every page, in the order asked for.
        $pdf = app(CertificateService::class)->bundle($issued->all());
        $this->assertStringStartsWith('%PDF', $pdf);
        $file = tempnam(sys_get_temp_dir(), 'run');
        file_put_contents($file, $pdf);
        try {
            $text = (string) shell_exec('pdftotext '.escapeshellarg($file).' -');
        } finally {
            @unlink($file);
        }
        $this->assertSame($issued->count(), substr_count($text, "\f"));
        $pages = explode("\f", $text);
        foreach ($issued->values() as $i => $c) {
            $this->assertStringContainsString($c->recipient->certificateName(), $pages[$i], "page {$i}");
        }
    }

    public function test_arabic_is_joined_on_an_english_page_too(): void
    {
        $shaper = app(\Ahl\Certificates\Support\TextShaper::class);
        $this->assertSame('Jane Doe', $shaper->visual('Jane Doe'));

        // An Arabic name in an English sentence: the Latin keeps its order, the Arabic
        // run is joined (presentation forms), and its words never wrap apart.
        $mixed = $shaper->visual('Organised by نادي البحرين');
        $this->assertStringStartsWith('Organised by ', $mixed);
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $mixed);
        $this->assertStringNotContainsString(' ', mb_substr($mixed, 13));
        $this->assertTrue(\Ahl\Certificates\Support\TextShaper::isArabicScript($mixed));

        // Text that starts in Arabic reads right to left, whole.
        $this->assertTrue(\Ahl\Certificates\Support\TextShaper::readsRtl('نادي Kyokushin'));
        $this->assertFalse(\Ahl\Certificates\Support\TextShaper::readsRtl('Kyokushin نادي'));

        // And the page sets it in Amiri, on an English certificate.
        $this->decidedDivision();
        $t = app(CertificateService::class)->templateFor('participation', $this->event);
        $t->elements = [['key' => 'text_arab01', 'kind' => 'text', 'style' => 'heading', 'text' => ['en' => 'شكراً لكل المشاركين']]];
        $png = app(CertificateService::class)->preview($t, 'en', $this->event, CertificateService::PURPOSE_DOWNLOAD, 40);
        $this->assertStringStartsWith("\x89PNG", $png);
    }

    /* ===================== MCP ===================== */

    public function test_mcp_mirrors_the_same_rules(): void
    {
        $this->decidedDivision();
        $stranger = $this->createUser();

        $this->assertArrayHasKey('error', $this->mcp(GenerateEventCertificatesTool::class, $stranger, ['event' => $this->event->uuid]));
        $this->assertArrayHasKey('error', $this->mcp(ListEventCertificatesTool::class, $stranger, ['event' => $this->event->uuid]));

        $this->assertSame(8, $this->mcp(GenerateEventCertificatesTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid])['created']);
        $this->assertCount(8, $this->mcp(ListEventCertificatesTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid])['certificates']);
        $this->assertSame(8, $this->mcp(ApproveEventCertificatesTool::class, $this->organiser->fresh(), ['event' => $this->event->uuid])['approved']);

        $mine = $this->mcp(ListMyCertificatesTool::class, $this->athletes['a']->fresh(), []);
        $this->assertCount(2, $mine['certificates']);   // participation + gold

        $uuid = $mine['certificates'][0]['uuid'];
        $this->assertArrayHasKey('error', $this->mcp(RevokeCertificateTool::class, $this->athletes['a']->fresh(), ['certificate' => $uuid, 'reason' => 'mine to revoke?']));
        $this->assertSame('revoked', $this->mcp(RevokeCertificateTool::class, $this->organiser->fresh(), ['certificate' => $uuid, 'reason' => 'Result overturned'])['status']);
    }
}

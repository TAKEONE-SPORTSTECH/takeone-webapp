<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCertificateSetting;
use App\Events\Models\EventClub;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventCertificates;
use App\Events\Support\EventParticipantBadges;
use Ahl\Certificates\Certificates as Engine;
use Tests\TestCase;

/**
 * The printables added 2026-10-15: officials' thank-you certificates (a third
 * certificate type, off by default) and participants' badges (the officials'
 * badge, drawn for competitors), each with its own design.
 */
class PrintablesTest extends TestCase
{
    private function event(): array
    {
        config(['certificates.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Printables Cup', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        return [$owner, $event->fresh()];
    }

    public function test_officials_get_one_thank_you_each_only_when_switched_on(): void
    {
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);
        $certs = app(EventCertificates::class);

        // Off by default: an existing event issues nothing new.
        $this->assertFalse($certs->settings($event)['officials']);
        $certs->generate($event, $owner);
        $this->assertSame(0, Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->count());

        $certs->saveSettings($event, ['officials' => true, 'participation' => false, 'podium' => false]);
        $certs->generate($event, $owner);
        $certs->generate($event, $owner);   // idempotent
        $made = Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->get();
        $this->assertCount(1, $made, 'one per person, however many jobs');
        $this->assertStringContainsString(' · ', (string) $made->first()->details['role']);
        $this->assertSame((string) $made->first()->details['role'], $certs->placeholders($made->first(), 'en')['role']);
    }

    public function test_each_participating_club_gets_one_thank_you_only_when_switched_on(): void
    {
        [$owner, $event] = $this->event();
        $platformClub = $this->createClub($this->createUser(), ['club_name' => 'Falcons Dojo', 'country' => 'BH', 'currency' => 'BHD']);
        $enter = function (string $name, ?int $tenantId = null) use ($event) {
            return ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser(['full_name' => $name])->id,
                'role' => 'participant', 'status' => 'joined', 'representing_tenant_id' => $tenantId]);
        };
        // Two athletes for a platform club, one of them in two divisions.
        $a = $enter('Ali One', $platformClub->id);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $a->user_id, 'role' => 'participant', 'status' => 'joined', 'representing_tenant_id' => $platformClub->id]);
        $enter('Ali Two', $platformClub->id);
        // One athlete under a club the organiser wrote down for the day.
        $written = EventClub::create(['event_id' => $event->id, 'name' => 'Okami Visitors', 'country' => 'BH', 'state' => 'listed']);
        $written->entrants()->attach($enter('Omar Visitor')->id);
        $certs = app(EventCertificates::class);

        // Off by default.
        $this->assertFalse($certs->settings($event)['clubs']);
        $this->assertSame(2, $certs->eligible($event)['clubs']);
        $certs->generate($event, $owner);
        $this->assertSame(0, Engine::model()::query()->forContext($event)->where('type', 'club_thanks')->count());

        // Switched on through the console's own endpoint; a stranger may not.
        $this->actingAs($this->createUser())->putJson(route('me.certificates.settings', ['club_event', $event->uuid]), ['clubs' => true])->assertStatus(404);
        $this->actingAs($owner)->putJson(route('me.certificates.settings', ['club_event', $event->uuid]), ['clubs' => true, 'participation' => false, 'podium' => false])
            ->assertOk()->assertJsonPath('settings.clubs', true);
        $certs->generate($event, $owner);
        $certs->generate($event, $owner);   // idempotent

        $made = Engine::model()::query()->forContext($event)->where('type', 'club_thanks')->with('recipient')->get()->keyBy(fn ($c) => $c->recipient->certificateName());
        $this->assertSame(['Falcons Dojo', 'Okami Visitors'], $made->keys()->sort()->values()->all());
        $this->assertSame('club', $made['Falcons Dojo']->recipient_type);
        $this->assertSame('event_club', $made['Okami Visitors']->recipient_type);
        $this->assertSame('2 athletes', $certs->placeholders($made['Falcons Dojo'], 'en')['athletes'], 'athletes, not entries');
        $this->assertSame('1 athlete', $certs->describe($made['Okami Visitors'], 'en'));

        // Linking the typed-in club to the platform club later never makes it a second certificate.
        $written->forceFill(['tenant_id' => $platformClub->id])->save();
        ClubEventRegistration::whereIn('id', $written->entrants()->pluck('club_event_registrations.id'))->update(['representing_tenant_id' => $platformClub->id]);
        $certs->generate($event, $owner);
        $this->assertSame(2, Engine::model()::query()->forContext($event)->where('type', 'club_thanks')->count());
        $written->forceFill(['tenant_id' => null])->save();

        // The organiser's list names the club; nobody is told as if they held it.
        $list = $this->actingAs($owner)->getJson(route('me.certificates.manage', ['club_event', $event->uuid]))->assertOk()->json('certificates');
        $this->assertContains('Falcons Dojo', collect($list)->pluck('holder')->all());
        $this->assertNull(\App\Certificates\Support\CertificateRealtime::holder($made['Falcons Dojo']));

        // The designer offers the club's logo, and no person's photo, on a club's certificate.
        $images = collect(app(\App\Certificates\Support\CertificatePrintable::class)->fields($event, 'club_thanks'))->where('type', 'image')->pluck('key')->all();
        $this->assertSame(['club_logo', 'club_flag', 'qr', 'logo'], $images);

        // Issued, its QR page names the club.
        $issued = app(\Ahl\Certificates\CertificateService::class)->approve($made['Okami Visitors'], $owner);
        $this->get(route('certificates.verify', ['token' => $issued->verify_token]))->assertOk()->assertSee('Okami Visitors');
    }

    public function test_issuing_one_kind_from_the_federation_menu_drafts_and_approves_that_kind_only(): void
    {
        [$owner, $event] = $this->event();
        $club = $this->createClub($this->createUser(), ['club_name' => 'Falcons Dojo', 'country' => 'BH', 'currency' => 'BHD']);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'participant', 'status' => 'joined', 'representing_tenant_id' => $club->id]);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        // The console's switches say officials only; the menu asks for the clubs' certificates by name.
        app(EventCertificates::class)->saveSettings($event, ['officials' => true, 'clubs' => false, 'participation' => false, 'podium' => false]);
        $p = ['club_event', $event->uuid];

        $this->actingAs($this->createUser())->postJson(route('me.certificates.generate', $p), ['type' => 'club_thanks'])->assertStatus(404);
        $this->actingAs($owner)->postJson(route('me.certificates.generate', $p), ['type' => 'nonsense'])->assertStatus(422);
        $this->actingAs($owner)->postJson(route('me.certificates.generate', $p), ['type' => 'club_thanks'])->assertOk()->assertJsonPath('created', 1);
        $this->assertSame(['club_thanks'], Engine::model()::query()->forContext($event)->pluck('type')->unique()->values()->all());

        // An officials' draft made from the console stays a draft when the clubs' are issued.
        $this->actingAs($owner)->postJson(route('me.certificates.generate', $p))->assertOk();
        $this->actingAs($owner)->postJson(route('me.certificates.approve', $p), ['all' => true, 'type' => 'club_thanks'])
            ->assertOk()->assertJsonPath('approved', 1)->assertJsonPath('remaining', 0);
        $status = Engine::model()::query()->forContext($event)->get()->mapWithKeys(fn ($c) => [$c->type => $c->status->value])->all();
        $this->assertSame(['club_thanks' => 'approved', 'official_thanks' => 'draft'], collect($status)->sortKeys()->all());
    }

    public function test_each_job_may_have_its_own_badge_and_certificate_design_and_falls_back_to_the_general_one(): void
    {
        [$owner, $event] = $this->event();
        config(['printables.enabled' => true]);
        $ref = $this->createUser(['full_name' => 'Rania Ref']);
        $typedJudge = $this->createUser(['full_name' => 'Judge From A Form']);
        $medic = $this->createUser(['full_name' => 'Doc Medic']);
        $refJob = EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'referee', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $typedJudge->id, 'role' => 'other', 'role_label' => 'Judge 2', 'compensation' => 'volunteer']);
        $medJob = EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $medic->id, 'role' => 'medical', 'compensation' => 'volunteer']);

        // The designer offers a badge and a certificate per job; "Judge 2" is the job "Judge".
        $printable = app(\App\Certificates\Support\CertificatePrintable::class);
        $slots = $printable->slots($event);
        foreach (['official_badge.referee', 'official_thanks.referee', 'official_badge.job-judge', 'official_thanks.medical'] as $slot) {
            $this->assertContains($slot, $slots);
        }
        $this->assertStringStartsWith('Official\'s badge — ', $printable->title($event, 'official_badge.referee'));
        $this->actingAs($owner)->get(\App\Printables\Designer::url($event, 'certificate', 'official_badge.referee'))->assertOk();

        // Only the referee's job has its own designs.
        $design = fn (string $slot) => \App\Printables\Models\PrintableDesign::create([
            'kind' => 'certificate', 'owner_type' => $event->getMorphClass(), 'owner_id' => $event->id, 'slot' => $slot,
            'format' => 'a4', 'orientation' => 'landscape', 'width_mm' => 297, 'height_mm' => 210, 'document' => ['pages' => [['elements' => []]]], 'revision' => 1,
        ]);
        $refBadge = $design('official_badge.referee');
        $refCert = $design('official_thanks.referee');
        $general = $design('official_thanks');

        $badges = app(\App\Events\Support\EventBadges::class);
        $pick = function ($o) use ($badges, $event) {
            $slots = new \ReflectionMethod($badges, 'jobSlots');
            $slots->setAccessible(true);
            $find = new \ReflectionMethod($badges, 'badgeDesign');
            $find->setAccessible(true);

            return $find->invoke($badges, $event, $slots->invoke($badges, $o));
        };
        $this->assertSame($refBadge->id, $pick($refJob)->id, 'the referee prints the referee\'s badge');
        $this->assertNull($pick($medJob)->id, 'the medic prints the general badge (its starting page here)');

        app(EventCertificates::class)->saveSettings($event, ['officials' => true, 'participation' => false, 'podium' => false]);
        app(EventCertificates::class)->generate($event, $owner);
        $certs = Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->with('recipient', 'context')->get()->keyBy(fn ($c) => $c->recipient->full_name);
        $designed = app(\App\Certificates\Support\DesignedCertificates::class);
        $this->assertSame($refCert->id, $designed->designFor($certs['Rania Ref'])->id);
        $this->assertSame($general->id, $designed->designFor($certs['Doc Medic'])->id);
        $this->assertSame($general->id, $designed->designFor($certs['Judge From A Form'])->id);
    }

    public function test_a_jobs_design_can_start_as_a_copy_of_another_with_its_own_pictures(): void
    {
        [$owner, $event] = $this->event();
        config(['printables.enabled' => true]);
        \Illuminate\Support\Facades\Storage::fake('local');
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'referee', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'medical', 'compensation' => 'volunteer']);

        $source = \App\Printables\Models\PrintableDesign::create([
            'kind' => 'certificate', 'owner_type' => $event->getMorphClass(), 'owner_id' => $event->id, 'slot' => 'official_thanks.referee',
            'format' => 'a4', 'orientation' => 'landscape', 'width_mm' => 297, 'height_mm' => 210, 'revision' => 3,
            'document' => ['pages' => [['elements' => []]]],
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('art/bg.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $art = new \App\Printables\Models\PrintableAsset(['design_id' => $source->id, 'disk' => 'local', 'path' => 'art/bg.png', 'mime' => 'image/png', 'width' => 1, 'height' => 1, 'bytes' => 68]);
        $art->uuid = (string) \Illuminate\Support\Str::uuid();
        $art->save();
        $source->forceFill(['document' => ['pages' => [['elements' => [['id' => 'bg', 'type' => 'image', 'asset' => $art->uuid, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]]]]]])->save();

        $ref = 'club_event.'.$event->uuid;
        $copy = fn (string $slot, string $from) => $this->actingAs($owner)->postJson(route('me.printables.copy', ['kind' => 'certificate', 'ref' => $ref, 'slot' => $slot]), ['from' => $from]);

        // The designer offers the copy on a job with no design yet.
        $this->actingAs($owner)->get(\App\Printables\Designer::url($event, 'certificate', 'official_thanks.medical'))->assertOk()->assertSee('copyUrl', false);

        $copy('official_thanks.medical', 'official_thanks.referee')->assertCreated();
        $made = \App\Printables\Designer::find($event, 'certificate', 'official_thanks.medical');
        $this->assertNotNull($made);
        $newArt = $made->assets()->first();
        $this->assertNotNull($newArt, 'the picture is copied with it');
        $this->assertNotSame($art->uuid, $newArt->uuid);
        $this->assertNotSame($art->path, $newArt->path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($newArt->path);
        $this->assertSame($newArt->uuid, $made->document['pages'][0]['elements'][0]['asset'] ?? null, 'pointed at its own copy');

        // Never over a design that exists, never a certificate into a badge, never for a stranger.
        $copy('official_thanks.medical', 'official_thanks.referee')->assertStatus(409);
        $copy('official_badge.medical', 'official_thanks.referee')->assertStatus(422);
        $this->actingAs($this->createUser())->postJson(route('me.printables.copy', ['kind' => 'certificate', 'ref' => $ref, 'slot' => 'official_thanks.referee']), ['from' => 'official_thanks.medical'])->assertNotFound();

        // Deleting the copy leaves the original's picture in place.
        $made->delete();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('art/bg.png');
    }

    public function test_one_jobs_certificates_make_their_own_print_file(): void
    {
        [$owner, $event] = $this->event();
        foreach ([['referee', null], ['referee', null], ['other', 'Organizer']] as [$role, $label]) {
            EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => $role, 'role_label' => $label, 'compensation' => 'volunteer']);
        }
        app(EventCertificates::class)->saveSettings($event, ['officials' => true, 'participation' => false, 'podium' => false]);
        app(EventCertificates::class)->generate($event, $owner);
        $this->actingAs($owner)->postJson(route('me.certificates.approve', ['club_event', $event->uuid]), ['all' => true])->assertOk();

        $bundles = app(\App\Certificates\Support\CertificateBundles::class);
        $provider = app(EventCertificates::class);
        $count = function (string $type) use ($bundles, $event) {
            $m = new \ReflectionMethod($bundles, 'issued');
            $m->setAccessible(true);

            return $m->invoke($bundles, $event, $type)->count();
        };
        $this->assertSame(3, $count('official_thanks'));
        $this->assertSame(2, $count('official_thanks.referee'));
        $this->assertSame(1, $count('official_thanks.job-organizer'));
        $this->assertNotNull($bundles->path($provider, $event, 'official_thanks.referee'));
        $this->assertFalse($bundles->accepts($provider, $event, 'official_thanks.nobody'));
        $this->assertFalse($bundles->accepts($provider, $event, 'participation.referee'));

        $p = ['context' => 'club_event', 'key' => $event->uuid];
        $this->actingAs($owner)->getJson(route('me.certificates.bundle', $p + ['type' => 'official_thanks.referee']))->assertOk()->assertJsonPath('type', 'official_thanks.referee');
        $this->actingAs($owner)->getJson(route('me.certificates.bundle', $p + ['type' => 'official_thanks.nobody']))->assertNotFound();
        $this->actingAs($this->createUser())->getJson(route('me.certificates.bundle', $p + ['type' => 'official_thanks.referee']))->assertNotFound();
    }

    public function test_certificates_can_be_issued_to_and_printed_for_chosen_people_only(): void
    {
        [$owner, $event] = $this->event();
        $people = collect(range(1, 3))->map(fn ($i) => $this->createUser(['full_name' => 'Official '.$i]));
        $people->each(fn ($u) => EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $u->id, 'role' => 'referee', 'compensation' => 'volunteer']));
        $p = ['club_event', $event->uuid];
        $key = fn ($u) => 'u:'.$u->uuid;

        // Two of three, by who they are: only they get one.
        $this->actingAs($owner)->postJson(route('me.certificates.issue-selected', $p), ['type' => 'official_thanks', 'holders' => [$key($people[0]), $key($people[1])]])
            ->assertOk()->assertJsonPath('issued', 2)->assertJsonCount(2, 'uuids');
        $issued = Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->get();
        $this->assertCount(2, $issued);
        $this->assertTrue($issued->every(fn ($c) => $c->status->value === 'approved'));
        $this->assertNotContains($people[2]->id, $issued->pluck('recipient_id')->all());

        // Asking again issues nothing new.
        $this->actingAs($owner)->postJson(route('me.certificates.issue-selected', $p), ['type' => 'official_thanks', 'holders' => [$key($people[0])]])
            ->assertOk()->assertJsonPath('issued', 0)->assertJsonCount(1, 'uuids');

        // A print file of the chosen: by person, or by certificate — the same set, the same file name.
        $byPerson = $this->actingAs($owner)->postJson(route('me.certificates.selection', $p), ['type' => 'official_thanks', 'holders' => [$key($people[0])]])->assertOk()->json();
        $byCert = $this->actingAs($owner)->postJson(route('me.certificates.selection', $p), ['type' => 'official_thanks', 'uuids' => [$issued->firstWhere('recipient_id', $people[0]->id)->uuid]])->assertOk()->json();
        $this->assertSame(1, $byPerson['count']);
        $this->assertSame($byPerson['type'], $byCert['type']);
        $this->assertStringStartsWith('official_thanks.sel-', $byPerson['type']);
        $this->actingAs($owner)->getJson(route('me.certificates.bundle', ['context' => 'club_event', 'key' => $event->uuid, 'type' => $byPerson['type']]))->assertOk();
        // Somebody with none issued: nothing to print.
        $this->actingAs($owner)->postJson(route('me.certificates.selection', $p), ['type' => 'official_thanks', 'holders' => [$key($people[2])]])->assertStatus(422);

        // Not the organiser: refused, and a made-up selection prints nothing.
        $this->actingAs($this->createUser())->postJson(route('me.certificates.issue-selected', $p), ['type' => 'official_thanks', 'holders' => [$key($people[2])]])->assertNotFound();
        $this->actingAs($owner)->getJson(route('me.certificates.bundle', ['context' => 'club_event', 'key' => $event->uuid, 'type' => 'official_thanks.sel-0123456789abcdef']))->assertNotFound();
    }

    public function test_an_issued_certificate_keeps_its_qr_and_number_however_often_it_is_issued_again(): void
    {
        [$owner, $event] = $this->event();
        $people = collect(range(1, 2))->map(fn ($i) => $this->createUser(['full_name' => 'Official '.$i]));
        $people->each(fn ($u) => EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $u->id, 'role' => 'referee', 'compensation' => 'volunteer']));
        $p = ['club_event', $event->uuid];
        $holders = $people->map(fn ($u) => 'u:'.$u->uuid)->all();
        $issue = fn () => $this->actingAs($owner)->postJson(route('me.certificates.issue-selected', $p), ['type' => 'official_thanks', 'holders' => $holders])->assertOk();
        $printed = fn () => Engine::model()::query()->forContext($event)->get()->mapWithKeys(fn ($c) => [$c->recipient_id => [$c->verify_token, $c->certificate_no]])->sortKeys()->all();

        $issue();
        $first = $printed();
        $this->assertCount(2, $first);

        // Everything deleted, then issued again — twice: the same QR and the same number each time.
        foreach ([1, 2] as $round) {
            $this->actingAs($owner)->deleteJson(route('me.certificates.destroy-all', $p), ['confirm' => 'delete-all'])->assertOk()->assertJsonPath('remaining', 0);
            $issue()->assertJsonPath('issued', 2);
            $this->assertSame($first, $printed(), "round {$round}");
        }

        // And the QR already printed opens the certificate again.
        $this->get(route('certificates.verify', $first[$people[0]->id][0]))->assertOk()->assertSee('Official 1');
    }

    public function test_the_designer_previews_a_design_with_a_real_holder_of_the_certificate(): void
    {
        [$owner, $event] = $this->event();
        config(['printables.enabled' => true]);
        $people = collect(['Mariam Real', 'Yusuf Real'])->map(fn ($n) => $this->createUser(['full_name' => $n]));
        $people->each(fn ($u) => EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $u->id, 'role' => 'referee', 'compensation' => 'volunteer']));
        $this->actingAs($owner)->postJson(route('me.certificates.issue-selected', ['club_event', $event->uuid]), ['type' => 'official_thanks', 'holders' => $people->map(fn ($u) => 'u:'.$u->uuid)->all()])->assertOk();
        $cert = Engine::model()::query()->forContext($event)->where('recipient_id', $people[0]->id)->first();

        $at = ['kind' => 'certificate', 'ref' => 'club_event.'.$event->uuid, 'slot' => 'official_thanks'];
        // The page offers it.
        $this->actingAs($owner)->get(route('me.printables.design', $at))->assertOk()->assertSee('people', false);

        // Who can be shown: the holders, searchable by name.
        $this->actingAs($owner)->getJson(route('me.printables.people', $at))->assertOk()->assertJsonCount(2, 'people');
        $this->actingAs($owner)->getJson(route('me.printables.people', $at + ['q' => 'mariam']))->assertOk()
            ->assertJsonCount(1, 'people')->assertJsonPath('people.0.label', 'Mariam Real')->assertJsonPath('people.0.id', $cert->uuid);

        // One of them, filled exactly as their certificate prints — their own QR link included.
        $values = $this->actingAs($owner)->getJson(route('me.printables.person', $at + ['id' => $cert->uuid]))->assertOk()->json('values');
        $this->assertSame('Mariam Real', $values['name']);
        $this->assertSame(route('certificates.verify', $cert->verify_token), $values['qr_text']);
        $this->assertStringStartsWith('data:image/', $values['qr']);

        // Another kind of certificate's holders are not offered here, and nobody else may look.
        $this->actingAs($owner)->getJson(route('me.printables.people', ['slot' => 'participation'] + $at))->assertOk()->assertJsonCount(0, 'people');
        $this->actingAs($owner)->getJson(route('me.printables.person', ['slot' => 'participation'] + $at + ['id' => $cert->uuid]))->assertNotFound();
        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson(route('me.printables.people', $at))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('me.printables.person', $at + ['id' => $cert->uuid]))->assertNotFound();
    }

    public function test_a_clubs_check_page_lists_its_team_and_only_staff_see_weights(): void
    {
        [$owner, $event] = $this->event();
        $event->forceFill(['entry_mode' => 'public'])->save();   // a public event (PublicEvent::isPublic)
        $club = $this->createClub($this->createUser(), ['club_name' => 'Falcons Dojo', 'country' => 'BH', 'currency' => 'BHD']);
        $kid = $this->createUser(['full_name' => 'Young Falcon', 'birthdate' => now()->subYears(11)->toDateString()]);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $kid->id, 'role' => 'participant', 'status' => 'joined', 'representing_tenant_id' => $club->id, 'weight' => 38.4]);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'participant', 'status' => 'joined']);

        $certs = app(EventCertificates::class);
        $stranger = $certs->teamOf($event->fresh(), 'falcons  dojo', null, 'en');
        $this->assertNotNull($stranger, 'a public event lists the team');
        $this->assertCount(1, $stranger['athletes'], 'only the club\'s own athletes');
        $this->assertSame('Young Falcon', $stranger['athletes'][0]['name']);
        $this->assertSame(11, $stranger['athletes'][0]['age']);
        $this->assertNull($stranger['athletes'][0]['weight'], 'a stranger never sees a weight');

        $staff = $certs->teamOf($event->fresh(), 'Falcons Dojo', $owner, 'en');
        $this->assertSame('38.4', $staff['athletes'][0]['weight']);
        $this->assertTrue($staff['staff']);
    }

    public function test_participants_get_one_badge_each(): void
    {
        [$owner, $event] = $this->event();
        $athlete = $this->createUser(['full_name' => 'Ali Athlete']);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $athlete->id, 'role' => 'participant', 'status' => 'joined']);
        $badges = app(EventParticipantBadges::class);

        $this->assertSame(1, $badges->printCount($event, 'participant_badge'));
        $this->assertSame(0, $badges->printCount($event, 'official_badge'));
        $this->assertSame('Ali Athlete', $badges->sampleFields($event, 'en')['name']);
        $this->assertSame(['participant_badge'], $badges->types());
    }

    public function test_each_design_room_opens_for_the_organiser(): void
    {
        [$owner, $event] = $this->event();

        $this->actingAs($owner)->get(route('me.certificates.design', ['event_participant_badges', $event->uuid]))->assertOk();
        $this->actingAs($owner)->get(route('me.certificates.design', ['club_event', $event->uuid]).'?type=official_thanks')->assertOk()
            ->assertSee('official_thanks', false);
        $this->actingAs($this->createUser())->get(route('me.certificates.design', ['event_participant_badges', $event->uuid]))->assertStatus(302);
    }

    public function test_a_design_can_have_its_own_page_size(): void
    {
        $badge = \Ahl\Certificates\Support\Formats::get('badge');
        $a5 = $badge->resized(210, 148);
        $this->assertSame([148.0, 210.0], array_map('floatval', $a5->page('portrait')));
        // Starting boxes scale with the page: the photo moves and grows in proportion.
        $photo = $badge->defaults(false, 'portrait')['photo'];
        $photo5 = $a5->defaults(false, 'portrait')['photo'];
        $this->assertEqualsWithDelta($photo['x'] * 148 / 105, $photo5['x'], 0.2);
        $this->assertEqualsWithDelta($photo['h'] * 210 / 148, $photo5['h'], 0.2);
        $this->assertSame('custom', $a5->paper);
        // Two A5 cards fit on an A4 sheet, ID cards many more; a 200 × 150 mm card only one, so one per page.
        $this->assertNotNull($a5->sheet);
        $this->assertNotNull($badge->resized(85.6, 54)->sheet);
        $this->assertNull($badge->resized(200, 150)->sheet);

        $this->assertNull(\Ahl\Certificates\Models\CertificateTemplate::sizeOf(['long' => 900, 'short' => 100]));
        $this->assertSame([210.0, 148.0], \Ahl\Certificates\Models\CertificateTemplate::sizeOf(['long' => 148, 'short' => 210]));
    }

    public function test_the_design_room_saves_and_draws_a_custom_size(): void
    {
        [$owner, $event] = $this->event();
        $p = ['club_event', $event->uuid, 'type' => 'participation'];
        $form = [
            'scope' => 'event', 'title' => ['en' => 'Certificate'], 'body' => ['en' => 'for {event}'],
            'ornament_level' => 'full', 'background' => 'plain', 'orientation' => 'landscape',
            'page_size' => ['long' => 210, 'short' => 148],
        ];

        $res = $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), $form)->assertOk();
        $t = collect($res->json('templates'))->firstWhere('type', 'participation');
        $this->assertEquals(['long' => 210, 'short' => 148], $t['page_size']);

        $png = $this->actingAs($owner)->get(route('me.certificates.design.preview', $p))->assertOk()->getContent();
        [$w, $h] = getimagesizefromstring($png);
        $this->assertEqualsWithDelta(210 / 148, $w / $h, 0.03, 'the preview is drawn on an A5 page');

        // Back to the format's own page: stored as nothing.
        $form['page_size'] = ['long' => 297, 'short' => 210];
        $res = $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), $form)->assertOk();
        $this->assertNull(collect($res->json('templates'))->firstWhere('type', 'participation')['page_size']);

        $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), ['page_size' => ['long' => 5000, 'short' => 10]] + $form)->assertStatus(422);
    }

    public function test_an_officials_badge_is_one_per_person_and_its_qr_opens_the_event(): void
    {
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref', 'nationality' => 'BH']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);
        $badges = app(\App\Events\Support\EventBadges::class);

        $this->assertCount(1, $badges->officials($event));
        $f = $badges->fields($badges->officials($event)->first(), 'en');
        $this->assertStringContainsString(' · ', $f['role'], 'both jobs on the one badge');
        $this->assertSame('Bahrain', $f['nationality']);
        $this->assertSame(route('me.events.show', ['event' => $event->uuid]), $f['qr']);
        $this->assertSame(route('me.events.show', ['event' => $event->uuid]), app(EventParticipantBadges::class)->sampleFields($event, 'en')['qr']);
    }

    public function test_each_design_offers_only_its_own_tokens(): void
    {
        [$owner, $event] = $this->event();
        $state = $this->actingAs($owner)->getJson(route('me.certificates.design.state', ['club_event', $event->uuid]))->assertOk()->json('templates');
        $thanks = collect($state)->firstWhere('type', 'official_thanks')['placeholders'];
        $this->assertContains('role', $thanks);
        $this->assertNotContains('division', $thanks);
        $this->assertNotContains('medal', $thanks);
        $this->assertContains('medal', collect($state)->firstWhere('type', 'podium')['placeholders']);
    }

    public function test_the_officials_pdf_is_for_the_organiser_only(): void
    {
        config(['events.bracket_pdf.enabled' => true, 'events.participants_pdf.enabled' => true]);
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref', 'nationality' => 'BH']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        $pdf = app(\App\Events\Support\OfficialsPdf::class);

        $groups = $pdf->groups($event, $owner, 'by_job');
        $this->assertSame('Sara Ref', $groups[0]['people'][0]['name']);
        $html = $pdf->html($event, $owner, $pdf->groups($event, $owner, 'signin'), 'signin');
        $this->assertStringContainsString('Sara Ref', $html);
        $this->assertStringNotContainsString((string) $ref->email, $html, 'no contact details print');

        $this->actingAs($this->createUser())->getJson(route('me.events.officials.pdf', [$event->uuid, 'kind' => 'all']))->assertNotFound();
        $this->actingAs($owner)->getJson(route('me.events.officials.pdf', [$event->uuid, 'kind' => 'nope']))->assertStatus(422);
    }
}

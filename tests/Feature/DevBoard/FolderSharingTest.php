<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Folder;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sharing a TakeNote folder (28 Oct 2026). The person it is shared with works in
 * the owner's notebook — notes, cards, the board — fenced to that folder: not
 * one other note of the owner's reaches them, by listing or by a uuid typed in,
 * and taking the share back takes effect on their very next request.
 */
class FolderSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notebooks::set(null);
        if (! Route::has('me.takenote.shares.store')) {
            $this->markTestSkipped('TAKENOTE_SHARING is off');
        }
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Notebooks::set(null);
        parent::tearDown();
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);
        Notebook::where('is_main', true)->update(['owner_id' => $u->id]);

        return $u;
    }

    /** A friend with a notebook of their own (invited by the owner, then signed in once). */
    private function friend(User $owner, string $email = 'amiga@example.com'): User
    {
        $this->fresh($owner)->postJson(route('me.takenote.friends.store'), ['email' => $email])->assertOk();
        $u = $this->createUser(['email' => $email]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->fresh($u)->getJson(route('me.takenote.data'))->assertOk(); // claims their notebook

        return $u;
    }

    /** A fresh request as this person: no notebook carried over from the last one. */
    private function fresh(User $u)
    {
        Notebooks::set(null);

        return $this->actingAs($u);
    }

    private function note(User $as, string $text, ?string $folder = null): string
    {
        $uuid = $this->fresh($as)->postJson(route('me.takenote.store'), ['html' => '<p>'.$text.'</p>'])->assertOk()->json('note.uuid');
        if ($folder) {
            $this->fresh($as)->postJson(route('me.takenote.file', $uuid), ['folder' => $folder])->assertOk();
        }

        return $uuid;
    }

    private function folder(User $as, string $name, ?string $parent = null): string
    {
        return $this->fresh($as)->postJson(route('me.takenote.folders.store'), array_filter(['name' => $name, 'parent' => $parent]))->assertOk()->json('folder.uuid');
    }

    private function titles(User $as): array
    {
        return collect($this->fresh($as)->getJson(route('me.takenote.data'))->assertOk()->json('notes'))->pluck('title')->sort()->values()->all();
    }

    private function open(User $as, ?string $share): void
    {
        $this->fresh($as)->postJson(route('me.takenote.space'), ['share' => $share])->assertOk();
    }

    private function folderId(string $uuid): int
    {
        return (int) Folder::withoutGlobalScopes()->where('uuid', $uuid)->value('id');
    }

    /** the owner with a shared folder "Plans" (a folder inside it), a private note elsewhere, and the friend it is shared with */
    private function scene(): array
    {
        $owner = $this->owner();
        $friend = $this->friend($owner);
        $plans = $this->folder($owner, 'Plans');
        $inside = $this->folder($owner, 'Venue', $plans);
        $this->note($owner, 'Shared plan', $plans);
        $this->note($owner, 'Venue list', $inside);
        $secret = $this->note($owner, 'Owner secret');
        $share = $this->fresh($owner)->postJson(route('me.takenote.shares.store', $plans), ['email' => 'amiga@example.com'])
            ->assertOk()->assertJsonPath('shares.0.joined', true)->json('shares.0.uuid');

        return compact('owner', 'friend', 'plans', 'inside', 'secret', 'share');
    }

    public function test_the_person_sees_only_the_shared_folder_and_works_in_it(): void
    {
        ['owner' => $owner, 'friend' => $friend, 'plans' => $plans, 'secret' => $secret, 'share' => $share] = $this->scene();

        // at home: their own notebook, and the folder waiting under "Shared with me"
        $home = $this->fresh($friend)->getJson(route('me.takenote.data'))->assertOk();
        $this->assertSame([], collect($home->json('notes'))->pluck('title')->all());
        $this->assertSame('Plans', $home->json('shared.0.name'));
        $this->assertNull($home->json('space'));

        // inside the shared folder: that folder's notes (and the folder inside it), nothing else
        $this->open($friend, $share);
        $this->assertSame(['Shared plan', 'Venue list'], $this->titles($friend));
        $in = $this->fresh($friend)->getJson(route('me.takenote.data'))->json();
        $this->assertSame(['Plans', 'Venue'], collect($in['folders'])->pluck('name')->sort()->values()->all());
        $this->assertSame('Plans', $in['space']['name']);
        $this->assertNotEmpty($in['lanes'], 'the owner\'s board lists, to work on together');

        // the owner's other note does not exist for them, however they ask
        $this->fresh($friend)->getJson(route('me.takenote.show', $secret))->assertNotFound();
        $this->fresh($friend)->patchJson(route('me.takenote.update', $secret), ['html' => '<p>x</p>', 'version' => 1])->assertNotFound();

        // what they write lands in the shared folder, in the owner's notebook — and the owner sees it
        $theirs = $this->note($friend, 'Friend idea');
        $row = Card::withoutGlobalScopes()->where('uuid', $theirs)->first();
        $this->assertSame($this->folderId($plans), (int) $row->folder_id);
        $this->assertSame((int) Notebook::where('is_main', true)->value('id'), (int) $row->notebook_id);
        $this->assertContains('Friend idea', $this->titles($owner));
        $this->open($friend, $share); // (one session for everybody in a test: the owner's visit closed it — each person has their own in a browser)

        // filing it "out of all folders" keeps it inside the fence
        $r = $this->fresh($friend)->postJson(route('me.takenote.file', $theirs), ['folder' => null]); $r->assertOk();
        $this->assertSame($this->folderId($plans), (int) Card::withoutGlobalScopes()->where('uuid', $theirs)->value('folder_id'));

        // a folder they make goes inside the shared one
        $sub = $this->folder($friend, 'Ideas');
        $this->assertSame($this->folderId($plans), (int) Folder::withoutGlobalScopes()->where('uuid', $sub)->value('parent_id'));
    }

    public function test_the_owners_lists_and_the_shared_folder_itself_stay_the_owners(): void
    {
        ['friend' => $friend, 'plans' => $plans, 'share' => $share] = $this->scene();
        $this->open($friend, $share);
        $lane = $this->fresh($friend)->getJson(route('me.takenote.data'))->json('lanes.0.uuid');

        $this->fresh($friend)->patchJson(route('me.margin.lanes.update', $lane), ['name' => 'Mine now'])->assertForbidden();
        $this->fresh($friend)->deleteJson(route('me.takenote.folders.destroy', $plans))->assertForbidden();
        $this->fresh($friend)->patchJson(route('me.takenote.folders.update', $plans), ['name' => 'Gone'])->assertForbidden();
        $this->assertSame('Plans', Folder::withoutGlobalScopes()->where('uuid', $plans)->value('name'));

        // and inside a shared folder there is nothing of theirs to share on
        $this->fresh($friend)->postJson(route('me.takenote.shares.store', $plans), ['email' => 'x@example.com'])->assertNotFound();
    }

    public function test_cards_made_on_the_board_inside_the_shared_folder_stay_in_it(): void
    {
        ['owner' => $owner, 'friend' => $friend, 'plans' => $plans, 'share' => $share] = $this->scene();
        $this->open($friend, $share);
        $lane = $this->fresh($friend)->getJson(route('me.takenote.data'))->json('lanes.0.uuid');

        $res = $this->fresh($friend)->postJson(route('me.margin.cards.store'), ['title' => 'Book the hall', 'lane' => $lane])->assertSuccessful();
        $card = Card::withoutGlobalScopes()->where('title', 'Book the hall')->first();
        $this->assertNotNull($card, $res->getContent());
        $this->assertSame($this->folderId($plans), (int) $card->folder_id);
        $this->assertContains('Book the hall', collect($this->fresh($owner)->getJson(route('me.takenote.data'))->json('cards'))->pluck('title')->all());
    }

    public function test_taking_the_share_back_works_on_their_next_request(): void
    {
        ['owner' => $owner, 'friend' => $friend, 'plans' => $plans, 'share' => $share] = $this->scene();
        $this->open($friend, $share);
        $shared = Card::withoutGlobalScopes()->where('folder_id', $this->folderId($plans))->value('uuid');
        $this->fresh($friend)->getJson(route('me.takenote.show', $shared))->assertOk();

        $this->fresh($owner)->deleteJson(route('me.takenote.shares.destroy', [$plans, $share]))->assertOk()->assertJsonCount(0, 'shares');

        $this->fresh($friend)->getJson(route('me.takenote.show', $shared))->assertNotFound();
        $back = $this->fresh($friend)->getJson(route('me.takenote.data'))->json();
        $this->assertNull($back['space']);
        $this->assertSame([], $back['shared']);
        $this->assertSame([], collect($back['notes'])->pluck('title')->all(), 'back in their own (empty) notebook');
    }

    public function test_a_picture_outside_the_shared_folder_is_not_found_by_its_own_address(): void
    {
        ['owner' => $owner, 'friend' => $friend, 'secret' => $secret, 'share' => $share] = $this->scene();
        $png = UploadedFile::fake()->image('p.png', 40, 40);
        $pic = $this->fresh($owner)->post(route('me.margin.pictures.store', $secret), ['image' => $png], ['Accept' => 'application/json'])->assertOk()->json('asset.uuid');
        $this->fresh($owner)->get(route('me.margin.pictures.show', $pic))->assertOk();

        $this->open($friend, $share);
        $this->fresh($friend)->getJson(route('me.margin.pictures.show', $pic))->assertNotFound(); // (a browser's page visit gets the same no, as the platform's redirect home)
    }

    public function test_an_unconfirmed_address_does_not_receive_a_share_meant_for_it(): void
    {
        $owner = $this->owner();
        $plans = $this->folder($owner, 'Plans');
        $this->note($owner, 'Shared plan', $plans);
        // somebody holding TakeNote under an address they never confirmed (as an invite-link account can)
        $u = $this->createUser(['email' => 'target@example.com']);
        $u->forceFill(['email_verified_at' => null])->save();
        Notebook::create(['owner_id' => $u->id, 'joined_at' => now(), 'via_link' => true]);

        $this->fresh($owner)->postJson(route('me.takenote.shares.store', $plans), ['email' => 'target@example.com'])
            ->assertOk()->assertJsonPath('shares.0.joined', false);
        $this->assertSame([], $this->fresh($u)->getJson(route('me.takenote.data'))->json('shared'), 'waits until the address is confirmed');

        $u->forceFill(['email_verified_at' => now()])->save();
        $this->assertSame('Plans', $this->fresh($u->fresh())->getJson(route('me.takenote.data'))->json('shared.0.name'));
    }

    public function test_a_share_cannot_be_opened_by_anybody_else(): void
    {
        ['share' => $share, 'owner' => $owner] = $this->scene();
        $stranger = $this->friend($owner, 'other@example.com');

        $this->fresh($stranger)->postJson(route('me.takenote.space'), ['share' => $share])->assertNotFound();
        $this->assertSame([], $this->titles($stranger));
    }

    public function test_sharing_with_somebody_without_takenote_invites_them_and_waits_for_them(): void
    {
        $owner = $this->owner();
        $plans = $this->folder($owner, 'Plans');
        $this->note($owner, 'Shared plan', $plans);

        $this->fresh($owner)->postJson(route('me.takenote.shares.store', $plans), ['email' => 'New.Person@Example.com'])
            ->assertOk()->assertJsonPath('shares.0.joined', false)->assertJsonPath('shares.0.email', 'new.person@example.com');
        $this->assertTrue(Notebook::where('email', 'new.person@example.com')->exists(), 'their own notebook is waiting too');

        $u = $this->createUser(['email' => 'new.person@example.com']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $data = $this->fresh($u)->getJson(route('me.takenote.data'))->assertOk()->json();
        $this->assertSame('Plans', $data['shared'][0]['name'], 'the waiting share became theirs');
        $this->open($u, $data['shared'][0]['uuid']);
        $this->assertSame(['Shared plan'], $this->titles($u));
    }

    public function test_a_friend_shares_only_with_people_who_already_have_takenote(): void
    {
        $owner = $this->owner();
        $friend = $this->friend($owner);
        $mine = $this->folder($friend, 'My trip');

        // a friend cannot bring new people into TakeNote by sharing…
        $this->fresh($friend)->postJson(route('me.takenote.shares.store', $mine), ['email' => 'nobody@example.com'])
            ->assertStatus(422)->assertJsonPath('message', __('devboard::messages.sh_needs_takenote'));
        // …but may share with somebody who already has it
        $this->fresh($friend)->postJson(route('me.takenote.shares.store', $mine), ['email' => $owner->email])
            ->assertOk()->assertJsonPath('shares.0.joined', true);
    }

    public function test_storage_is_counted_and_the_limit_refuses_what_does_not_fit(): void
    {
        $owner = $this->owner();
        $note = $this->note($owner, 'Pictures');
        $data = $this->fresh($owner)->getJson(route('me.takenote.data'))->json('storage');
        $this->assertSame(1024 * 1024 * 1024, $data['limit']);
        $this->assertSame(0, $data['used']);

        config(['devboard.quota_mb' => 1]); // one megabyte, for the test
        $big = UploadedFile::fake()->create('big.jpg', 1500, 'image/jpeg');
        $this->fresh($owner)->post(route('me.margin.pictures.store', $note), ['image' => $big], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', __('devboard::messages.st_full'));
        // an attached file the same — refused, nothing stored
        $pdf = UploadedFile::fake()->createWithContent('big.pdf', "%PDF-1.4\n".str_repeat('x', 1200 * 1024)."\n%%EOF");
        $this->fresh($owner)->post(route('me.takenote.cards.files.store', $note), ['file' => $pdf], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', __('devboard::messages.st_full'));
        $this->assertSame(0, \App\DevBoard\Models\Attachment::withoutGlobalScopes()->count());
    }

    public function test_inside_a_shared_folder_the_room_is_the_owners_and_the_gauge_stays_ones_own(): void
    {
        ['friend' => $friend, 'share' => $share] = $this->scene();
        $main = (int) Notebook::where('is_main', true)->value('id');
        $own = (int) Notebook::where('owner_id', $friend->id)->value('id');
        \Illuminate\Support\Facades\DB::table('devboard_assets')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'card_id' => Card::withoutGlobalScopes()->where('notebook_id', $main)->value('id'),
            'path' => 'x.jpg', 'width' => 1, 'height' => 1, 'bytes' => 5000, 'kind' => 'image', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->open($friend, $share);
        $d = $this->fresh($friend)->getJson(route('me.takenote.data'))->json();
        $this->assertSame(5000, $d['room']['used'], 'what an upload here would count against: the owner\'s notebook');
        $this->assertSame(\App\DevBoard\Support\Storage::used($own), $d['storage']['used'], 'the gauge: their own');
    }
}

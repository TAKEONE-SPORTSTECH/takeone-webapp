<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Lane;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TakeNote: the board as a phone notebook. The same cards and notes, kept to
 * board members; the app page answers signed-out people with its own sign-in
 * and carries no notes itself.
 */
class TakeNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.data')) {
            $this->markTestSkipped('DEVBOARD_ENABLED / TAKENOTE_ENABLED is off');
        }
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    public function test_a_new_note_is_a_note_in_the_notebook_not_a_card(): void
    {
        $me = $this->owner();

        $res = $this->actingAs($me)->postJson(route('me.takenote.store'), [
            'html' => '<h1>Seminar tickets</h1><p onclick="x()">Forty <b>seats</b><script>alert(1)</script></p>',
        ])->assertOk();

        $card = Card::where('uuid', $res->json('note.uuid'))->firstOrFail();
        $this->assertSame('Seminar tickets', $card->title);
        $this->assertSame('note', $card->kind);
        $this->assertSame(Lane::orderBy('position')->first()->id, $card->lane_id); // kept for when it becomes a card
        $this->assertSame('Inbox', $res->json('note.lane.name'));

        $html = $res->json('note.html');
        $this->assertStringContainsString('<b>seats</b>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('script', $html);

        $this->actingAs($me)->getJson(route('me.takenote.data'))
            ->assertOk()
            ->assertJsonPath('notes.0.uuid', $card->uuid)
            ->assertJsonPath('notes.0.title', 'Seminar tickets')
            ->assertJsonPath('cards', [])
            ->assertJsonPath('me.id', $me->id);
    }

    public function test_writing_follows_the_version_and_keeps_the_title_in_step(): void
    {
        $me = $this->owner();
        $uuid = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>First idea</p>'])->json('note.uuid');

        $this->actingAs($me)->patchJson(route('me.takenote.update', $uuid), ['version' => 1, 'html' => '<h1>Better idea</h1><p>more</p>'])
            ->assertOk()->assertJsonPath('note.version', 2)->assertJsonPath('note.title', 'Better idea');

        // A stale save is refused, and the note as it now stands comes back.
        $this->actingAs($me)->patchJson(route('me.takenote.update', $uuid), ['version' => 1, 'html' => '<p>old</p>'])
            ->assertStatus(409)->assertJsonPath('note.version', 2)->assertJsonPath('note.title', 'Better idea');

        $this->assertSame('Better idea', Card::where('uuid', $uuid)->value('title'));
    }

    public function test_a_card_made_on_the_board_opens_from_its_title_and_gets_a_page_on_first_write(): void
    {
        $me = $this->owner();
        $lane = Lane::create(['name' => 'Planned', 'color' => '#3b82f6', 'position' => 1024]);
        $card = Card::create(['lane_id' => $lane->id, 'title' => 'Fix <b>this</b>', 'priority' => 'normal', 'position' => 1024, 'created_by' => $me->id]);

        $this->actingAs($me)->getJson(route('me.takenote.show', $card))
            ->assertOk()
            ->assertJsonPath('note.version', 0)
            ->assertJsonPath('note.html', '<h1>Fix &lt;b&gt;this&lt;/b&gt;</h1>');

        $this->actingAs($me)->patchJson(route('me.takenote.update', $card), ['version' => 0, 'html' => '<h1>Fix this now</h1>'])
            ->assertOk()->assertJsonPath('note.version', 1);

        $this->assertSame(1, Item::where('card_id', $card->id)->where('type', 'note')->count());
        $this->assertSame('Fix this now', $card->fresh()->title);
    }

    public function test_only_an_empty_fresh_note_of_your_own_can_be_discarded(): void
    {
        $me = $this->owner();
        $other = $this->createUser();
        BoardMember::create(['user_id' => $other->id, 'ink' => '#f472b6']);

        $blank = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => ''])->json('note.uuid');
        $written = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>keep me</p>'])->json('note.uuid');

        // Somebody else's blank note: refused.
        $this->actingAs($other)->deleteJson(route('me.takenote.destroy', $blank).'?discard=1')->assertStatus(422);
        // A note with words in it: refused.
        $this->actingAs($me)->deleteJson(route('me.takenote.destroy', $written).'?discard=1')->assertStatus(422);
        $this->assertNotNull(Card::where('uuid', $written)->first());

        $this->actingAs($me)->deleteJson(route('me.takenote.destroy', $blank).'?discard=1')->assertOk();
        $this->assertNull(Card::withTrashed()->where('uuid', $blank)->first());
    }

    public function test_a_binned_note_comes_back_with_undo(): void
    {
        $me = $this->owner();
        $uuid = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>maybe later</p>'])->json('note.uuid');

        $this->actingAs($me)->deleteJson(route('me.takenote.destroy', $uuid))->assertOk();
        $this->actingAs($me)->getJson(route('me.takenote.data'))->assertOk()->assertJsonCount(0, 'notes');
        $this->actingAs($me)->getJson(route('me.takenote.show', $uuid))->assertNotFound();

        $this->actingAs($me)->postJson(route('me.takenote.restore', $uuid))->assertOk()->assertJsonPath('note.title', 'maybe later');
        $this->actingAs($me)->getJson(route('me.takenote.data'))->assertOk()->assertJsonCount(1, 'notes');
    }

    public function test_add_a_card_lands_at_the_bottom_of_its_list_and_takes_a_label(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>first</p>'])->assertOk();
        $lanes = $this->actingAs($me)->getJson(route('me.takenote.data'))->json('lanes');
        $planned = $lanes[1]['uuid'];

        $a = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>Order mats</h1>', 'lane' => $planned])->assertOk()->json('note');
        $b = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>Book the hall</h1>', 'lane' => $planned])->assertOk()->json('note');
        $this->assertSame($planned, $b['lane']['uuid']);
        $this->assertGreaterThan($a['position'], $b['position'], 'each new card goes under the last one');
        $this->assertSame('Order mats', $a['title']);

        $this->actingAs($me)->postJson(route('me.takenote.place', $a['uuid']), ['priority' => 'urgent'])->assertOk()->assertJsonPath('note.priority', 'urgent');
        $this->actingAs($me)->postJson(route('me.takenote.place', $a['uuid']), ['priority' => 'loud'])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>x</p>', 'lane' => '00000000-0000-4000-8000-000000000000'])->assertNotFound();
    }

    public function test_the_desk_notebook_is_for_board_people_only(): void
    {
        $me = $this->owner();

        $page = $this->actingAs($me)->get(route('me.notes'))->assertOk();
        $this->assertStringContainsString('"desk":true', $page->getContent());
        $page->assertDontSee('profile-dropdown-item', false); // full screen: not inside the site's navbar

        $stranger = $this->createUser();
        $other = $this->actingAs($stranger)->get(route('me.notes'));
        $this->assertContains($other->status(), [302, 404]);
        $other->assertDontSee('tn-config', false);
        auth()->logout();
        $this->get(route('me.notes'))->assertRedirect();
    }

    public function test_people_outside_the_board_get_nothing(): void
    {
        $me = $this->owner();
        $stranger = $this->createUser();
        $uuid = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>private</p>'])->json('note.uuid');

        // A web 404 becomes the platform's redirect home; either way, no app.
        $page = $this->actingAs($stranger)->get(route('takenote.app'));
        $this->assertContains($page->status(), [302, 404]);
        $page->assertDontSee('tn-config', false);
        $this->actingAs($stranger)->getJson(route('me.takenote.data'))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('me.takenote.show', $uuid))->assertNotFound();
        $this->actingAs($stranger)->patchJson(route('me.takenote.update', $uuid), ['version' => 1, 'html' => '<p>mine now</p>'])->assertNotFound();
        $this->actingAs($stranger)->deleteJson(route('me.takenote.destroy', $uuid))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.takenote.store'), ['html' => '<p>x</p>'])->assertNotFound();

        // what is stored, whoever signed in last (model reads are scoped to the signed-in person's notebook)
        $this->assertSame('private', Card::withoutGlobalScopes()->where('uuid', $uuid)->value('title'));
    }

    public function test_a_note_is_sent_to_the_board_for_someone_by_a_date(): void
    {
        $me = $this->owner();
        $sara = $this->createUser(['full_name' => 'Sara Ahmed']);
        BoardMember::create(['user_id' => $sara->id, 'ink' => '#34d399']);
        $stranger = $this->createUser();

        $a = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>first</p>'])->json('note.uuid');
        $b = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>second</p>'])->json('note.uuid');
        $c = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>third</p>'])->json('note.uuid');
        $lanes = $this->actingAs($me)->getJson(route('me.takenote.data'))->assertOk()->json('lanes');
        $this->assertSame('Inbox', $lanes[0]['name']);
        $planned = $lanes[1]['uuid'];

        // a note is not on the board until it is made a card
        $this->actingAs($me)->postJson(route('me.takenote.place', $a), ['lane' => $planned])->assertStatus(422);
        foreach ([$a, $b, $c] as $u) {
            $this->actingAs($me)->postJson(route('me.takenote.convert', $u), ['to' => 'card'])->assertOk();
        }

        // sent: top of Planned, for Sara, due on a day
        $this->actingAs($me)->postJson(route('me.takenote.place', $a), ['lane' => $planned, 'assignee' => $sara->id, 'due' => '2026-11-05'])
            ->assertOk()->assertJsonPath('note.lane.uuid', $planned)->assertJsonPath('note.assignee.id', $sara->id)->assertJsonPath('note.due_on', '2026-11-05');
        // another goes to the top of Planned, above it
        $this->actingAs($me)->postJson(route('me.takenote.place', $b), ['lane' => $planned])->assertOk();
        $this->assertTrue(Card::where('uuid', $b)->value('position') < Card::where('uuid', $a)->value('position'));
        // dropped between two notes
        $this->actingAs($me)->postJson(route('me.takenote.place', $c), ['lane' => $planned, 'above' => $b, 'below' => $a])->assertOk();
        $pos = fn ($u) => Card::where('uuid', $u)->value('position');
        $this->assertTrue($pos($b) < $pos($c) && $pos($c) < $pos($a));

        // only what is sent changes; null clears
        $this->actingAs($me)->postJson(route('me.takenote.place', $a), ['due' => null])->assertOk()
            ->assertJsonPath('note.due_on', null)->assertJsonPath('note.assignee.id', $sara->id)->assertJsonPath('note.lane.uuid', $planned);

        // refused: somebody who is not on the board, a bad date, a neighbour from another lane
        $this->actingAs($me)->postJson(route('me.takenote.place', $a), ['assignee' => $stranger->id])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.place', $a), ['due' => '05/11/2026'])->assertStatus(422);
        $inbox = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>still in inbox</p>'])->json('note.uuid');
        $this->actingAs($me)->postJson(route('me.takenote.convert', $inbox), ['to' => 'card'])->assertOk();
        $this->actingAs($me)->postJson(route('me.takenote.place', $c), ['lane' => $planned, 'above' => $inbox])->assertNotFound();

        // and nobody outside the board can move anything
        $this->actingAs($stranger)->postJson(route('me.takenote.place', $a), ['lane' => $lanes[0]['uuid']])->assertNotFound();
        $this->assertSame($planned, Card::withoutGlobalScopes()->where('uuid', $a)->first()->lane()->withoutGlobalScopes()->first()->uuid);
    }

    public function test_signed_out_the_app_shows_its_own_sign_in_and_no_notes(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>secret plan</p>']);
        auth()->logout();

        $res = $this->get(route('takenote.app'))->assertOk();
        $res->assertSee('action="'.url('/login').'"', false);
        $res->assertDontSee('secret plan');
        $this->assertSame(route('takenote.app'), session('url.intended'));

        $this->getJson(route('me.takenote.data'))->assertUnauthorized();
    }

    public function test_the_app_page_manifest_and_worker_stay_inside_their_own_folder(): void
    {
        $me = $this->owner();

        $this->actingAs($me)->get(route('takenote.app'))->assertOk()->assertSee('tn-config', false)->assertSee('rel="manifest"', false);

        $m = $this->get(route('takenote.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('/me/takenote', $m['scope']);
        $this->assertStringStartsWith('/me/takenote', $m['start_url']);
        $this->assertSame('standalone', $m['display']);

        $this->get(route('takenote.worker'))->assertOk()->assertHeader('Service-Worker-Allowed', '/me/takenote');
        $this->get('/takenote')->assertRedirect('/me/takenote');
    }
}

<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Card;
use App\DevBoard\Services\CardTools;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Trello's card tools on TakeNote's board: labels, members and watchers,
 * dates with a reminder, complete, cover style, archive, templates, copy,
 * checklist items, reactions and @mentions — cards only, board people only,
 * and nothing for outsiders.
 */
class CardToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.cards.labels')) {
            $this->markTestSkipped('TAKENOTE_ENABLED is off');
        }
        Storage::fake('local');
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function card(User $me, string $title = 'Mats'): array
    {
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>'.$title.'</h1><p>Order two more.</p>'])->assertOk()->json('note');

        return $this->actingAs($me)->postJson(route('me.takenote.convert', $note['uuid']), ['to' => 'card'])->assertOk()->json('note');
    }

    public function test_labels_are_named_coloured_and_several_to_a_card(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $labels = $this->actingAs($me)->getJson(route('me.takenote.data'))->assertOk()->json('labels');
        $this->assertCount(6, $labels, 'Trello\'s starting six');

        $new = $this->actingAs($me)->postJson(route('me.takenote.labels.store'), ['name' => 'Design', 'color' => '#6e5dc6'])->assertOk()->json('label');
        $this->actingAs($me)->postJson(route('me.takenote.labels.store'), ['name' => 'x', 'color' => 'red'])->assertStatus(422);

        $this->actingAs($me)->postJson(route('me.takenote.cards.labels', $card['uuid']), ['label' => $new['uuid'], 'on' => true])->assertOk();
        $note = $this->actingAs($me)->postJson(route('me.takenote.cards.labels', $card['uuid']), ['label' => $labels[0]['uuid'], 'on' => true])->assertOk()->json('note');
        $this->assertEqualsCanonicalizing(['Design', 'Urgent'], array_column($note['labels'], 'name'));

        $this->actingAs($me)->patchJson(route('me.takenote.labels.update', $new['uuid']), ['name' => 'UI'])->assertOk();
        $this->actingAs($me)->deleteJson(route('me.takenote.labels.destroy', $labels[0]['uuid']))->assertOk();
        $cards = $this->actingAs($me)->getJson(route('me.takenote.data'))->json('cards');
        $this->assertSame(['UI'], array_column($cards[0]['labels'], 'name'), 'renamed everywhere; a deleted label leaves every card');
    }

    public function test_members_watchers_and_the_people_they_must_be(): void
    {
        $me = $this->owner();
        $her = $this->createUser();
        BoardMember::create(['user_id' => $her->id, 'ink' => '#e0457b', 'added_by' => $me->id]);
        $stranger = $this->createUser();
        $card = $this->card($me);

        $note = $this->actingAs($me)->postJson(route('me.takenote.cards.members', $card['uuid']), ['user' => $her->id, 'on' => true])->assertOk()->json('note');
        $this->assertSame([$her->id], array_column($note['members'], 'id'));
        $this->assertSame($her->id, $note['assignee']['id'], 'the one-person field follows the first member');
        $this->assertSame(1, UserNotification::where('user_id', $her->id)->where('type', 'devboard_member')->count());

        $this->actingAs($me)->postJson(route('me.takenote.cards.members', $card['uuid']), ['user' => $stranger->id, 'on' => true])->assertStatus(422);

        $note = $this->actingAs($her)->postJson(route('me.takenote.cards.watch', $card['uuid']), ['on' => true])->assertOk()->json('note');
        $this->assertSame([$her->id], $note['watchers']);

        // a comment reaches the people it names and everyone watching
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.store', $card['uuid']), ['body' => '@Her look', 'mentions' => [$her->id, $stranger->id]])->assertOk();
        $this->assertSame(1, UserNotification::where('user_id', $her->id)->where('type', 'devboard_mention')->count());
        $this->assertSame(0, UserNotification::where('user_id', $her->id)->where('type', 'devboard_comment')->count(), 'named and watching: told once');
        $this->assertSame(0, UserNotification::where('user_id', $stranger->id)->count(), 'nobody outside the board hears anything');

        $this->actingAs($stranger)->postJson(route('me.takenote.cards.watch', $card['uuid']), ['on' => true])->assertNotFound();
    }

    public function test_dates_complete_and_the_reminder(): void
    {
        $me = $this->owner();
        $her = $this->createUser();
        BoardMember::create(['user_id' => $her->id, 'ink' => '#e0457b', 'added_by' => $me->id]);
        $card = $this->card($me);
        $this->actingAs($me)->postJson(route('me.takenote.cards.members', $card['uuid']), ['user' => $her->id, 'on' => true])->assertOk();

        $this->actingAs($me)->postJson(route('me.takenote.cards.dates', $card['uuid']), ['start' => '2026-11-09', 'due' => '2026-11-05'])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.cards.dates', $card['uuid']), ['due' => '2026-11-05', 'time' => '25:00'])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.cards.dates', $card['uuid']), ['due' => '2026-11-05', 'reminder' => 7])->assertStatus(422);

        $soon = now()->addMinutes(30);
        $note = $this->actingAs($me)->postJson(route('me.takenote.cards.dates', $card['uuid']), [
            'start' => $soon->copy()->subDay()->toDateString(), 'due' => $soon->toDateString(), 'time' => $soon->format('H:i'), 'reminder' => 60,
        ])->assertOk()->json('note');
        $this->assertSame($soon->format('H:i'), $note['due_time']);
        $this->assertSame(60, $note['reminder']);

        $tools = app(CardTools::class);
        $this->assertSame(1, $tools->remindDue());
        $this->assertSame(0, $tools->remindDue(), 'once per due moment');
        $this->assertSame(1, UserNotification::where('user_id', $her->id)->where('type', 'devboard_reminder')->count());

        $note = $this->actingAs($me)->postJson(route('me.takenote.cards.complete', $card['uuid']), ['done' => true])->assertOk()->json('note');
        $this->assertTrue($note['done']);
        $this->assertSame($card['lane']['uuid'], $note['lane']['uuid'], 'complete is a tick: the card stays where it is');
    }

    public function test_archive_templates_copy_cover_and_reactions(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $lanes = $this->actingAs($me)->getJson(route('me.takenote.data'))->json('lanes');

        $note = $this->actingAs($me)->postJson(route('me.takenote.cards.cover-style', $card['uuid']), ['color' => '#0c66e4', 'size' => 'full'])->assertOk()->json('note');
        $this->assertSame(['#0c66e4', 'full'], [$note['cover_color'], $note['cover_size']]);
        $this->actingAs($me)->postJson(route('me.takenote.cards.cover-style', $card['uuid']), ['color' => 'url(x)'])->assertStatus(422);

        // a checklist with an item, then a template, then a card from it
        $list = $this->actingAs($me)->postJson(route('me.takenote.cards.checklists.store', $card['uuid']), ['title' => 'Steps'])->json('checklists.0.uuid');
        $step = $this->actingAs($me)->postJson(route('me.takenote.cards.steps.store', [$card['uuid'], $list]), ['text' => 'Call the shop'])->json('checklists.0.steps.0.uuid');
        $this->actingAs($me)->patchJson(route('me.takenote.cards.steps.plan', [$card['uuid'], $step]), ['assignee' => $me->id, 'due' => '2026-11-05'])->assertOk()
            ->assertJsonPath('checklists.0.steps.0.due', '2026-11-05');
        $this->actingAs($me)->postJson(route('me.takenote.cards.template', $card['uuid']), ['on' => true])->assertOk()->assertJsonPath('note.template', true);
        $copy = $this->actingAs($me)->postJson(route('me.takenote.cards.copy', $card['uuid']), ['title' => 'Mats, again', 'lane' => $lanes[1]['uuid'], 'where' => 'top', 'keep' => ['checklists']])->assertOk()->json('note');
        $this->assertSame('Mats, again', $copy['title']);
        $this->assertSame([0, 1], $copy['steps']);
        $this->assertFalse($copy['template'], 'a card from a template is an ordinary card');

        // a checklist item becomes a card of its own
        $this->actingAs($me)->postJson(route('me.takenote.cards.steps.card', [$card['uuid'], $step]))->assertOk()->assertJsonPath('note.title', 'Call the shop');

        // reactions: one of each kind per person, a second tap takes it back
        $comment = $this->actingAs($me)->postJson(route('me.takenote.cards.comments.store', $card['uuid']), ['body' => 'Done?'])->json('comment.uuid');
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.react', [$card['uuid'], $comment]), ['kind' => 'heart'])->assertOk()->assertJsonPath('comment.reactions.heart', [$me->id]);
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.react', [$card['uuid'], $comment]), ['kind' => 'heart'])->assertOk()->assertJsonPath('comment.reactions', []);
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.react', [$card['uuid'], $comment]), ['kind' => '<script>'])->assertStatus(422);
        // any standard emoji is a reaction too (skin tones included); words and markup are not
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.react', [$card['uuid'], $comment]), ['kind' => "\u{1F44D}\u{1F3FD}"])->assertOk()
            ->assertJsonPath('comment.reactions', ["\u{1F44D}\u{1F3FD}" => [$me->id]]);
        $this->actingAs($me)->postJson(route('me.takenote.cards.comments.react', [$card['uuid'], $comment]), ['kind' => 'hello'])->assertStatus(422);

        // archive: off the board, into Archived items, and back
        $this->actingAs($me)->postJson(route('me.takenote.cards.archive', $card['uuid']), ['on' => true])->assertOk();
        $board = array_column($this->actingAs($me)->getJson(route('me.takenote.data'))->json('cards'), 'uuid');
        $this->assertNotContains($card['uuid'], $board);
        $this->assertSame([$card['uuid']], array_column($this->actingAs($me)->getJson(route('me.takenote.archived'))->json('cards'), 'uuid'));
        $this->actingAs($me)->postJson(route('me.takenote.cards.archive', $card['uuid']), ['on' => false])->assertOk();
        $this->assertContains($card['uuid'], array_column($this->actingAs($me)->getJson(route('me.takenote.data'))->json('cards'), 'uuid'));
    }

    public function test_only_cards_take_the_tools_and_outsiders_reach_nothing(): void
    {
        $me = $this->owner();
        $stranger = $this->createUser();
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>Idea</h1>'])->json('note');
        $this->actingAs($me)->postJson(route('me.takenote.cards.complete', $note['uuid']), ['done' => true])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.cards.archive', $note['uuid']), ['on' => true])->assertStatus(422);
        $this->assertNull(Card::where('uuid', $note['uuid'])->value('done_at'));

        $card = $this->card($me);
        foreach ([
            ['me.takenote.cards.complete', ['done' => true]], ['me.takenote.cards.dates', ['due' => '2026-11-05']],
            ['me.takenote.cards.archive', ['on' => true]], ['me.takenote.cards.copy', ['title' => 'x', 'lane' => $card['lane']['uuid'], 'where' => 'top']],
        ] as [$name, $body]) {
            $this->actingAs($stranger)->postJson(route($name, $card['uuid']), $body)->assertNotFound();
        }
        $this->actingAs($stranger)->getJson(route('me.takenote.archived'))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.takenote.labels.store'), ['name' => 'x', 'color' => '#0c66e4'])->assertNotFound();
    }
}

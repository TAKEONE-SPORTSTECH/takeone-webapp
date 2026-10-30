<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Notebook;
use App\DevBoard\Models\Relation;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Named connections between notes (29 Oct 2026) — Trilium's relations. Two notes
 * are joined with a few words; one connection per pair; it never touches either
 * note's text. Only notes the person can open right now can be connected or
 * seen connected: not another notebook's, not the owner's notes outside a shared
 * folder, not a note in the bin.
 */
class NoteRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notebooks::set(null);
        if (! Route::has('me.takenote.relations.store')) {
            $this->markTestSkipped('TAKENOTE_RELATIONS is off');
        }
        Mail::fake();
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

    private function friend(User $owner, string $email = 'amiga@example.com'): User
    {
        $this->fresh($owner)->postJson(route('me.takenote.friends.store'), ['email' => $email])->assertOk();
        $u = $this->createUser(['email' => $email]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->fresh($u)->getJson(route('me.takenote.data'))->assertOk();

        return $u;
    }

    private function fresh(User $u)
    {
        Notebooks::set(null);

        return $this->actingAs($u);
    }

    private function note(User $as, string $title, ?string $folder = null): string
    {
        $uuid = $this->fresh($as)->postJson(route('me.takenote.store'), ['html' => '<h1>'.$title.'</h1><p>words</p>'])->assertOk()->json('note.uuid');
        if ($folder) {
            $this->fresh($as)->postJson(route('me.takenote.file', $uuid), ['folder' => $folder])->assertOk();
        }

        return $uuid;
    }

    private function relations(User $as): array
    {
        return $this->fresh($as)->getJson(route('me.takenote.data'))->assertOk()->json('relations');
    }

    public function test_two_notes_are_connected_named_renamed_turned_round_and_removed(): void
    {
        $me = $this->owner();
        $plan = $this->note($me, 'Plan');
        $budget = $this->note($me, 'Budget');
        $before = $this->fresh($me)->getJson(route('me.takenote.show', $plan))->json('note.html');

        $rel = $this->fresh($me)->postJson(route('me.takenote.relations.store'), ['from' => $plan, 'to' => $budget, 'label' => 'depends on'])
            ->assertOk()->assertJsonPath('relation.from', $plan)->assertJsonPath('relation.to', $budget)->assertJsonPath('relation.label', 'depends on')
            ->json('relation.uuid');
        $this->assertSame([['uuid' => $rel, 'from' => $plan, 'to' => $budget, 'label' => 'depends on']], $this->relations($me));
        $this->assertSame($before, $this->fresh($me)->getJson(route('me.takenote.show', $plan))->json('note.html'), 'the note\'s text is untouched');

        // connecting the same two again — either way round — names the one connection there is
        $this->fresh($me)->postJson(route('me.takenote.relations.store'), ['from' => $budget, 'to' => $plan, 'label' => "  feeds\n into  "])
            ->assertOk()->assertJsonPath('relation.uuid', $rel)->assertJsonPath('relation.from', $budget)->assertJsonPath('relation.label', 'feeds into');
        $this->assertSame(1, Relation::withoutGlobalScopes()->count());

        $this->fresh($me)->patchJson(route('me.takenote.relations.update', $rel), ['flip' => true])->assertOk()->assertJsonPath('relation.from', $plan);
        $this->fresh($me)->patchJson(route('me.takenote.relations.update', $rel), ['label' => ''])->assertOk()->assertJsonPath('relation.label', null);
        $this->fresh($me)->patchJson(route('me.takenote.relations.update', $rel), ['label' => str_repeat('x', 41)])->assertStatus(422);

        $this->fresh($me)->deleteJson(route('me.takenote.relations.destroy', $rel))->assertOk();
        $this->assertSame([], $this->relations($me));
    }

    public function test_a_note_cannot_be_connected_to_itself(): void
    {
        $me = $this->owner();
        $plan = $this->note($me, 'Plan');
        $this->fresh($me)->postJson(route('me.takenote.relations.store'), ['from' => $plan, 'to' => $plan])->assertStatus(422);
        $this->assertSame(0, Relation::withoutGlobalScopes()->count());
    }

    public function test_another_notebook_cannot_connect_read_or_change_my_notes_connections(): void
    {
        $me = $this->owner();
        $plan = $this->note($me, 'Plan');
        $budget = $this->note($me, 'Budget');
        $rel = $this->fresh($me)->postJson(route('me.takenote.relations.store'), ['from' => $plan, 'to' => $budget])->json('relation.uuid');

        $friend = $this->friend($me);
        $theirs = $this->note($friend, 'Mine');
        $this->assertSame([], $this->relations($friend));
        // my notes are not found from their notebook, valid uuids or not
        $this->fresh($friend)->postJson(route('me.takenote.relations.store'), ['from' => $theirs, 'to' => $plan])->assertNotFound();
        $this->fresh($friend)->postJson(route('me.takenote.relations.store'), ['from' => $plan, 'to' => $budget])->assertNotFound();
        $this->fresh($friend)->patchJson(route('me.takenote.relations.update', $rel), ['label' => 'mine now'])->assertNotFound();
        $this->fresh($friend)->deleteJson(route('me.takenote.relations.destroy', $rel))->assertNotFound();
        $this->assertNull(Relation::withoutGlobalScopes()->where('uuid', $rel)->value('label'));
        $this->assertSame(1, Relation::withoutGlobalScopes()->count());
    }

    public function test_in_a_shared_folder_only_connections_inside_it_are_seen_or_made(): void
    {
        if (! Route::has('me.takenote.shares.store')) {
            $this->markTestSkipped('TAKENOTE_SHARING is off');
        }
        $owner = $this->owner();
        $friend = $this->friend($owner);
        $plans = $this->fresh($owner)->postJson(route('me.takenote.folders.store'), ['name' => 'Plans'])->json('folder.uuid');
        $a = $this->note($owner, 'Shared A', $plans);
        $b = $this->note($owner, 'Shared B', $plans);
        $secret = $this->note($owner, 'Owner secret');
        $this->fresh($owner)->postJson(route('me.takenote.relations.store'), ['from' => $a, 'to' => $secret, 'label' => 'private'])->assertOk();
        $share = $this->fresh($owner)->postJson(route('me.takenote.shares.store', $plans), ['email' => 'amiga@example.com'])->json('shares.0.uuid');

        $this->fresh($friend)->postJson(route('me.takenote.space'), ['share' => $share])->assertOk();
        $this->assertSame([], $this->relations($friend), 'a connection reaching outside the folder is not theirs to see');
        $this->fresh($friend)->postJson(route('me.takenote.relations.store'), ['from' => $a, 'to' => $secret])->assertNotFound();

        $made = $this->fresh($friend)->postJson(route('me.takenote.relations.store'), ['from' => $a, 'to' => $b, 'label' => 'together'])->assertOk();
        $this->assertSame(Notebooks::for($owner)->id, Relation::withoutGlobalScopes()->where('uuid', $made->json('relation.uuid'))->value('notebook_id'));
        $this->assertSame(['together'], collect($this->relations($friend))->pluck('label')->all());

        // the owner sees both
        $this->open($owner);
        $this->assertSame(['private', 'together'], collect($this->relations($owner))->pluck('label')->sort()->values()->all());
    }

    private function open(User $as): void
    {
        $this->fresh($as)->postJson(route('me.takenote.space'), ['share' => null])->assertOk();
    }

    public function test_a_note_in_the_bin_takes_its_connections_out_of_sight_and_back(): void
    {
        $me = $this->owner();
        $plan = $this->note($me, 'Plan');
        $budget = $this->note($me, 'Budget');
        $this->fresh($me)->postJson(route('me.takenote.relations.store'), ['from' => $plan, 'to' => $budget])->assertOk();

        $this->fresh($me)->deleteJson(route('me.takenote.destroy', $budget))->assertOk();
        $this->assertSame([], $this->relations($me));
        $this->fresh($me)->postJson(route('me.takenote.restore', $budget))->assertOk();
        $this->assertCount(1, $this->relations($me));
    }
}

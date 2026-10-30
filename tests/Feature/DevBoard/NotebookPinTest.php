<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Card;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pinning a note to the top of the notebook (the list laid out like the notes
 * apps, 28 Oct 2026). A pin is not an edit: the note keeps its place in time.
 */
class NotebookPinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.pin')) {
            $this->markTestSkipped('TAKENOTE_ENABLED is off');
        }
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    public function test_a_note_is_pinned_and_unpinned_without_moving_in_time(): void
    {
        $me = $this->owner();
        $uuid = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>Hall TV</p>'])->assertOk()->json('note.uuid');
        $before = Card::where('uuid', $uuid)->value('updated_at');
        $this->travel(2)->hours();

        $this->actingAs($me)->postJson(route('me.takenote.pin', $uuid), ['pinned' => true])
            ->assertOk()->assertJsonPath('note.pinned', true);
        $this->assertNotNull(Card::where('uuid', $uuid)->value('pinned_at'));
        $this->assertSame((string) $before, (string) Card::where('uuid', $uuid)->value('updated_at'));

        $listed = collect($this->actingAs($me)->getJson(route('me.takenote.data'))->assertOk()->json('notes'))->firstWhere('uuid', $uuid);
        $this->assertTrue($listed['pinned']);

        $this->actingAs($me)->postJson(route('me.takenote.pin', $uuid), ['pinned' => false])
            ->assertOk()->assertJsonPath('note.pinned', false);
        $this->assertNull(Card::where('uuid', $uuid)->value('pinned_at'));
    }

    public function test_outsiders_cannot_pin_and_the_value_is_checked(): void
    {
        $me = $this->owner();
        $uuid = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>x</p>'])->json('note.uuid');

        $this->actingAs($me)->postJson(route('me.takenote.pin', $uuid), [])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.pin', $uuid), ['pinned' => 'yes please'])->assertStatus(422);

        $stranger = $this->createUser();
        $this->actingAs($stranger)->postJson(route('me.takenote.pin', $uuid), ['pinned' => true])->assertNotFound();
        $this->assertNull(Card::where('uuid', $uuid)->value('pinned_at'));
    }
}

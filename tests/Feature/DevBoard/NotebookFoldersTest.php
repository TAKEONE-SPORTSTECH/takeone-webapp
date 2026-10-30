<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Folder;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The notebook's folders (a tree, four deep at most; deleting one never
 * deletes a note) and the links between notes that the note map draws.
 */
class NotebookFoldersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.folders.store')) {
            $this->markTestSkipped('TAKENOTE_ENABLED is off');
        }
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function folder(User $me, string $name, ?string $parent = null): string
    {
        return $this->actingAs($me)->postJson(route('me.takenote.folders.store'), ['name' => $name, 'parent' => $parent])->assertOk()->json('folder.uuid');
    }

    public function test_notes_go_into_nested_folders_and_a_deleted_folder_hands_its_notes_up(): void
    {
        $me = $this->owner();
        $work = $this->folder($me, 'Work');
        $events = $this->folder($me, 'Events', $work);
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>Hall screens</p>'])->json('note.uuid');

        $this->actingAs($me)->postJson(route('me.takenote.file', $note), ['folder' => $events])->assertOk()->assertJsonPath('note.folder', $events);
        $data = $this->actingAs($me)->getJson(route('me.takenote.data'))->json();
        $this->assertSame($work, collect($data['folders'])->firstWhere('uuid', $events)['parent']);
        $this->assertSame($events, $data['notes'][0]['folder']);

        // a folder cannot go inside itself, nor inside its own child
        $this->actingAs($me)->patchJson(route('me.takenote.folders.update', $work), ['parent' => $events])->assertStatus(422);
        $this->actingAs($me)->patchJson(route('me.takenote.folders.update', $work), ['parent' => $work])->assertStatus(422);
        $this->actingAs($me)->patchJson(route('me.takenote.folders.update', $events), ['name' => 'Hall events', 'color' => '#e0457b'])->assertOk();

        // four deep at most
        $three = $this->folder($me, 'Three', $events);
        $four = $this->folder($me, 'Four', $three);
        $this->actingAs($me)->postJson(route('me.takenote.folders.store'), ['name' => 'Five', 'parent' => $four])->assertStatus(422);

        // deleting a folder deletes no note: it moves up a level
        $this->actingAs($me)->deleteJson(route('me.takenote.folders.destroy', $events))->assertOk();
        $this->assertSame(Folder::where('uuid', $work)->value('id'), Card::where('uuid', $note)->value('folder_id'));
        $this->assertSame(Folder::where('uuid', $work)->value('id'), Folder::where('uuid', $three)->value('parent_id'));
        $this->assertSame(1, Card::count());
    }

    public function test_a_note_knows_the_notes_it_links_to(): void
    {
        $me = $this->owner();
        $a = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>Mats</p>'])->json('note.uuid');
        $b = $this->actingAs($me)->postJson(route('me.takenote.store'), [
            'html' => '<p>See <a href="https://stage.takeone.bh/me/takenote?note='.$a.'">Mats</a> and <a href="https://evil.test/?note='.$a.'">x</a></p>',
        ])->json('note');

        $this->assertSame([$a], $b['links']);
    }

    public function test_cards_and_outsiders_cannot_be_filed(): void
    {
        $me = $this->owner();
        $work = $this->folder($me, 'Work');
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>x</p>'])->json('note.uuid');
        $this->actingAs($me)->postJson(route('me.takenote.convert', $note), ['to' => 'card'])->assertOk();
        $this->actingAs($me)->postJson(route('me.takenote.file', $note), ['folder' => $work])->assertStatus(422);

        $stranger = $this->createUser();
        $this->actingAs($stranger)->postJson(route('me.takenote.folders.store'), ['name' => 'Mine'])->assertNotFound();
        $this->actingAs($stranger)->deleteJson(route('me.takenote.folders.destroy', $work))->assertNotFound();
    }
}

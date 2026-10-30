<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Attachment;
use App\DevBoard\Models\BoardMember;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notes are not cards: the notebook holds notes, the board holds cards, and a
 * note can be turned into a card and back. The open card's attachments (files
 * checked by their bytes, links http(s) only), comments, checklists and cover
 * — each looked up only inside its own card, and none of it for outsiders.
 */
class CardBackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.cards.show')) {
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

    private function note(User $me): array
    {
        return $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>Mats</h1><p>Order two more.</p>'])->assertOk()->json('note');
    }

    private function card(User $me): array
    {
        $note = $this->note($me);

        return $this->actingAs($me)->postJson(route('me.takenote.convert', $note['uuid']), ['to' => 'card'])->assertOk()->json('note');
    }

    public function test_notes_and_cards_are_kept_apart_and_a_note_can_become_a_card_and_back(): void
    {
        $me = $this->owner();
        $note = $this->note($me);
        $this->assertSame('note', $note['kind']);
        $data = $this->actingAs($me)->getJson(route('me.takenote.data'))->json();
        $this->assertSame([$note['uuid']], array_column($data['notes'], 'uuid'));
        $this->assertSame([], $data['cards'], 'a note is not on the board');

        $planned = $data['lanes'][1]['uuid'];
        $card = $this->actingAs($me)->postJson(route('me.takenote.convert', $note['uuid']), ['to' => 'card', 'lane' => $planned])->assertOk()->json('note');
        $this->assertSame('card', $card['kind']);
        $this->assertSame($planned, $card['lane']['uuid']);
        $data = $this->actingAs($me)->getJson(route('me.takenote.data'))->json();
        $this->assertSame([], $data['notes'], 'a card is not in the notebook');
        $this->assertSame([$note['uuid']], array_column($data['cards'], 'uuid'));
        $this->actingAs($me)->postJson(route('me.takenote.convert', $note['uuid']), ['to' => 'card'])->assertStatus(422);

        $this->actingAs($me)->postJson(route('me.takenote.convert', $note['uuid']), ['to' => 'note'])->assertOk()->assertJsonPath('note.kind', 'note');

        // "Add a card" on the board makes a card straight away
        $added = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<h1>Book hall</h1>', 'lane' => $planned])->json('note');
        $this->assertSame('card', $added['kind']);
    }

    public function test_files_are_taken_by_their_bytes_and_served_only_as_downloads(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $pdf = UploadedFile::fake()->createWithContent('Mat layout.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $a = $this->actingAs($me)->post(route('me.takenote.cards.files.store', $card['uuid']), ['file' => $pdf], ['Accept' => 'application/json'])
            ->assertOk()->json('attachment');
        $this->assertSame('Mat layout.pdf', $a['name']);
        $row = Attachment::where('uuid', $a['uuid'])->first();
        $this->assertStringEndsWith($a['uuid'].'.pdf', $row->path, 'the stored name is the server\'s, never the person\'s');
        Storage::disk('local')->assertExists($row->path);

        $res = $this->actingAs($me)->get(route('me.takenote.cards.files.show', [$card['uuid'], $a['uuid']]))->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));

        foreach ([
            ['shell.php', '<?php system($_GET["c"]);'],
            ['fake.pdf', '<html><script>alert(1)</script></html>'],
            ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            ['report.docx', "PK\x03\x04 not really a document"],
        ] as [$name, $bytes]) {
            $this->actingAs($me)->post(route('me.takenote.cards.files.store', $card['uuid']), ['file' => UploadedFile::fake()->createWithContent($name, $bytes)], ['Accept' => 'application/json'])
                ->assertStatus(422);
        }
        $this->assertSame(1, Attachment::count());

        // removing it takes the file away too
        $this->actingAs($me)->deleteJson(route('me.takenote.cards.attachments.destroy', [$card['uuid'], $a['uuid']]))->assertOk();
        Storage::disk('local')->assertMissing($row->path);
    }

    public function test_links_must_be_web_addresses(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->postJson(route('me.takenote.cards.links.store', $card['uuid']), ['url' => 'https://www.samsung.com/qm55c', 'name' => 'Hall TV spec'])
            ->assertOk()->assertJsonPath('attachment.host', 'www.samsung.com')->assertJsonPath('attachment.name', 'Hall TV spec');
        foreach (['javascript:alert(1)', 'data:text/html,<b>x</b>', 'file:///etc/passwd', 'not a url', 'ftp://x.test/a'] as $bad) {
            $this->actingAs($me)->postJson(route('me.takenote.cards.links.store', $card['uuid']), ['url' => $bad])->assertStatus(422);
        }
    }

    public function test_comments_checklists_and_cover_stay_inside_their_card(): void
    {
        $me = $this->owner();
        $sara = $this->createUser();
        BoardMember::create(['user_id' => $sara->id, 'ink' => '#34d399']);
        $card = $this->card($me);
        $other = $this->card($me);

        $c = $this->actingAs($me)->postJson(route('me.takenote.cards.comments.store', $card['uuid']), ['body' => 'Mat 3 too'])->assertOk()->json('comment');
        $this->actingAs($sara)->patchJson(route('me.takenote.cards.comments.update', [$card['uuid'], $c['uuid']]), ['body' => 'mine now'])->assertForbidden();
        $this->actingAs($me)->patchJson(route('me.takenote.cards.comments.update', [$other['uuid'], $c['uuid']]), ['body' => 'x'])->assertNotFound();
        $this->actingAs($me)->patchJson(route('me.takenote.cards.comments.update', [$card['uuid'], $c['uuid']]), ['body' => 'Mat 3 as well'])->assertOk();

        $lists = $this->actingAs($me)->postJson(route('me.takenote.cards.checklists.store', $card['uuid']), ['title' => 'Saturday'])->json('checklists');
        $list = $lists[0]['uuid'];
        $steps = $this->actingAs($me)->postJson(route('me.takenote.cards.steps.store', [$card['uuid'], $list]), ['text' => 'Mat 1'])->json('checklists.0.steps');
        $this->actingAs($me)->patchJson(route('me.takenote.cards.steps.update', [$card['uuid'], $steps[0]['uuid']]), ['done' => true])->assertJsonPath('checklists.0.steps.0.done', true);
        $this->actingAs($me)->patchJson(route('me.takenote.cards.steps.update', [$other['uuid'], $steps[0]['uuid']]), ['done' => false])->assertNotFound();

        $this->actingAs($me)->postJson(route('me.takenote.cards.cover', $card['uuid']), ['uuid' => '00000000-0000-4000-8000-000000000000'])->assertStatus(422);

        $detail = $this->actingAs($me)->getJson(route('me.takenote.cards.show', $card['uuid']))->assertOk()->json('card');
        $this->assertSame('Mat 3 as well', $detail['comments'][0]['body']);
        $this->assertSame('Saturday', $detail['checklists'][0]['title']);
        $this->assertContains('converted', array_column($detail['activity'], 'action'));
    }

    public function test_outsiders_reach_none_of_it(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $stranger = $this->createUser();

        $this->actingAs($stranger)->getJson(route('me.takenote.cards.show', $card['uuid']))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.takenote.cards.comments.store', $card['uuid']), ['body' => 'hi'])->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.takenote.cards.links.store', $card['uuid']), ['url' => 'https://x.test'])->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.takenote.convert', $card['uuid']), ['to' => 'note'])->assertNotFound();
    }

    public function test_a_note_takes_files_of_many_kinds_but_never_programs(): void
    {
        $me = $this->owner();
        $note = $this->note($me);
        $mp3 = UploadedFile::fake()->createWithContent('voice memo.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xff\xfb\x90\x64".str_repeat("\x00", 413), 6));
        $this->actingAs($me)->post(route('me.takenote.cards.files.store', $note['uuid']), ['file' => $mp3], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('attachment.ext', 'mp3')->assertJsonPath('note.kind', 'note')->assertJsonPath('note.attachments', 1);
        $this->actingAs($me)->getJson(route('me.takenote.show', $note['uuid']))->assertOk()->assertJsonPath('note.attachment_list.0.name', 'voice memo.mp3');

        foreach (['run.exe' => "MZ\x90\x00".str_repeat("\x00", 200), 'page.html' => '<html><script>alert(1)</script></html>', 'x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>', 'fake.mp3' => '<?php echo 1;'] as $name => $bytes) {
            $this->actingAs($me)->post(route('me.takenote.cards.files.store', $note['uuid']), ['file' => UploadedFile::fake()->createWithContent($name, $bytes)], ['Accept' => 'application/json'])
                ->assertStatus(422);
        }
        $this->assertSame(1, Attachment::count());
    }
}

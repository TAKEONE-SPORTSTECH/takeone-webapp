<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Lane;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Drawings: a sketch, or marks on a photo. Shown as a picture (re-encoded like
 * any other), kept with its strokes so it can be changed, its photo kept apart
 * so marks are never burnt into the only copy — and the strokes checked hard.
 */
class MarginInkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.margin.ink.store')) {
            $this->markTestSkipped('DEVBOARD_ENABLED is off');
        }
        Storage::fake('local');
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function card(User $by): Card
    {
        $lane = Lane::first() ?? Lane::create(['name' => 'Inbox', 'color' => '#7c6bf5', 'position' => 1024]);

        return Card::create(['lane_id' => $lane->id, 'title' => 'Idea', 'priority' => 'normal', 'position' => 1024, 'created_by' => $by->id]);
    }

    private function png(int $w = 120, int $h = 160): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 251, 247, 239));
        imageline($img, 5, 5, $w - 5, $h - 5, imagecolorallocate($img, 29, 21, 48));
        ob_start(); imagepng($img); $bytes = ob_get_clean();

        return UploadedFile::fake()->createWithContent('drawing.png', $bytes);
    }

    private function strokes(): array
    {
        return [
            ['t' => 'pen', 'c' => '#1D1530', 'w' => 7, 'p' => [[10, 10, 0.5], [40.25, 60], [80, 120]]],
            ['t' => 'arrow', 'c' => '#ff3b5c', 'w' => 6, 'p' => [[100, 20], [20, 100]]],
            ['t' => 'text', 'c' => '#ffd84d', 'w' => 54, 'p' => [[30, 40]], 'x' => "cut\u{0007} off!"],
        ];
    }

    private function save(User $me, Card $card, array $fields)
    {
        return $this->actingAs($me)->post(route('me.margin.ink.store', $card), $fields + ['image' => $this->png()], ['Accept' => 'application/json']);
    }

    public function test_a_sketch_is_kept_as_a_picture_with_its_strokes(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $res = $this->save($me, $card, ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($this->strokes())])->assertOk();
        $asset = Asset::where('uuid', $res->json('asset.uuid'))->firstOrFail();

        $this->assertSame('image', $asset->kind);
        Storage::disk('local')->assertExists($asset->path); // re-encoded like every picture
        $this->assertStringEndsWith('.webp', $asset->path);

        $ink = $this->actingAs($me)->getJson(route('me.margin.ink.show', $asset))->assertOk()->json('ink');
        $this->assertSame('sketch', $ink['kind']);
        $this->assertNull($ink['base']);
        $this->assertCount(3, $ink['strokes']);
        $this->assertSame('#1d1530', $ink['strokes'][0]['c'], 'colours are normalised');
        $this->assertSame('cut  off!', $ink['strokes'][2]['x'], 'control characters are dropped from text');
    }

    public function test_marks_on_a_photo_keep_the_photo_underneath(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $photo = $this->actingAs($me)->post(route('me.margin.pictures.store', $card), ['image' => $this->png(300, 200)], ['Accept' => 'application/json'])->json('asset.uuid');

        // A plain photo opens for marking up over itself, with no strokes yet.
        $this->actingAs($me)->getJson(route('me.margin.ink.show', $photo))
            ->assertOk()->assertJsonPath('ink.kind', 'markup')->assertJsonPath('ink.base', $photo)->assertJsonCount(0, 'ink.strokes');

        $marked = $this->save($me, $card, ['kind' => 'markup', 'w' => 300, 'h' => 200, 'base' => $photo, 'strokes' => json_encode($this->strokes())])
            ->assertOk()->json('asset.uuid');

        $this->assertNotSame($photo, $marked);
        $this->actingAs($me)->getJson(route('me.margin.ink.show', $marked))->assertOk()->assertJsonPath('ink.base', $photo);
        Storage::disk('local')->assertExists(Asset::where('uuid', $photo)->value('path'));
    }

    public function test_a_markup_must_name_a_photo_of_its_own_card(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $other = $this->card($me);
        $foreign = $this->actingAs($me)->post(route('me.margin.pictures.store', $other), ['image' => $this->png()], ['Accept' => 'application/json'])->json('asset.uuid');
        $s = json_encode($this->strokes());

        $this->save($me, $card, ['kind' => 'markup', 'w' => 120, 'h' => 160, 'strokes' => $s])->assertStatus(422);
        $this->save($me, $card, ['kind' => 'markup', 'w' => 120, 'h' => 160, 'base' => $foreign, 'strokes' => $s])->assertStatus(422);
        $this->save($me, $card, ['kind' => 'sketch', 'w' => 120, 'h' => 160, 'base' => $foreign, 'strokes' => $s])->assertStatus(422);
    }

    public function test_strokes_out_of_shape_are_refused(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $bad = [
            [['t' => 'script', 'c' => '#000000', 'w' => 4, 'p' => [[1, 1]]]],
            [['t' => 'pen', 'c' => 'red', 'w' => 4, 'p' => [[1, 1]]]],
            [['t' => 'pen', 'c' => '#000000', 'w' => 999, 'p' => [[1, 1]]]],
            [['t' => 'pen', 'c' => '#000000', 'w' => 4, 'p' => [[1, '2']]]],
            [['t' => 'pen', 'c' => '#000000', 'w' => 4, 'p' => [[1, 99999]]]],
            [['t' => 'pen', 'c' => '#000000', 'w' => 4, 'p' => []]],
            [['t' => 'text', 'c' => '#000000', 'w' => 40, 'p' => [[1, 1]], 'x' => str_repeat('a', 301)]],
            [['t' => 'pen', 'c' => '#000000', 'w' => 4, 'p' => array_fill(0, 5001, [1, 1])]],
            'not a list',
        ];
        foreach ($bad as $strokes) {
            $this->save($me, $card, ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($strokes)])->assertStatus(422);
        }
        $this->assertSame(0, Asset::count());
    }

    public function test_annotation_objects_keep_their_shape_style_and_turn(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $objects = [
            ['t' => 'arrow', 'c' => '#ff3b5c', 'w' => 7, 's' => 'curve', 'p' => [[10, 10], [200, 120], [80, 160]]],
            ['t' => 'arrow', 'c' => '#ff3b5c', 'w' => 7, 's' => 'block', 'p' => [[10, 10], [200, 120]]],
            ['t' => 'rect', 'c' => '#3fd3c1', 'w' => 6, 'r' => 0.5236, 'p' => [[20, 20], [300, 200]]],
            ['t' => 'bubble', 'c' => '#ffd84d', 'w' => 40, 'p' => [[40, 40], [400, 200], [60, 300]], 'x' => "Cut off here!\nTwo lines"],
            ['t' => 'step', 'c' => '#7c6bf5', 'w' => 30, 'n' => 2, 'p' => [[500, 500]]],
            ['t' => 'blur', 'c' => '#000000', 'w' => 1, 'p' => [[100, 100], [250, 160]]],
        ];
        $uuid = $this->save($me, $card, ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($objects)])->assertOk()->json('asset.uuid');
        $ink = $this->actingAs($me)->getJson(route('me.margin.ink.show', $uuid))->json('ink.strokes');

        $this->assertSame('curve', $ink[0]['s']);
        $this->assertCount(3, $ink[0]['p']);
        $this->assertSame('block', $ink[1]['s']);
        $this->assertSame(0.5236, $ink[2]['r']);
        $this->assertSame("Cut off here!\nTwo lines", $ink[3]['x'], 'a bubble keeps its line breaks');
        $this->assertSame(2, $ink[4]['n']);

        $bad = [
            [['t' => 'arrow', 'c' => '#000000', 'w' => 4, 's' => 'zigzag', 'p' => [[1, 1], [2, 2]]]],
            [['t' => 'arrow', 'c' => '#000000', 'w' => 4, 'p' => [[1, 1]]]],
            [['t' => 'step', 'c' => '#000000', 'w' => 20, 'p' => [[1, 1]]]],
            [['t' => 'step', 'c' => '#000000', 'w' => 20, 'n' => 0, 'p' => [[1, 1]]]],
            [['t' => 'bubble', 'c' => '#000000', 'w' => 20, 'p' => [[1, 1], [9, 9]], 'x' => 'no tail']],
            [['t' => 'rect', 'c' => '#000000', 'w' => 4, 'r' => 'spin', 'p' => [[1, 1], [9, 9]]]],
            [['t' => 'rect', 'c' => '#000000', 'w' => 4, 'r' => 99, 'p' => [[1, 1], [9, 9]]]],
        ];
        foreach ($bad as $strokes) {
            $this->save($me, $card, ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($strokes)])->assertStatus(422);
        }
    }

    public function test_outsiders_cannot_read_or_add_drawings(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $uuid = $this->save($me, $card, ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($this->strokes())])->json('asset.uuid');

        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson(route('me.margin.ink.show', $uuid))->assertNotFound();
        $this->actingAs($stranger)->post(route('me.margin.ink.store', $card), ['image' => $this->png(), 'kind' => 'sketch', 'w' => 10, 'h' => 10, 'strokes' => '[]'], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_takenote_shows_a_drawing_as_a_drawing(): void
    {
        if (! Route::has('me.takenote.data')) {
            $this->markTestSkipped('TAKENOTE_ENABLED is off');
        }
        $me = $this->owner();
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => ''])->json('note');
        $asset = $this->save($me, Card::where('uuid', $note['uuid'])->first(), ['kind' => 'sketch', 'w' => 1200, 'h' => 1600, 'strokes' => json_encode($this->strokes())])->json('asset.uuid');

        $title = $this->actingAs($me)->patchJson(route('me.takenote.update', $note['uuid']), ['version' => $note['version'], 'html' => '<img data-asset="'.$asset.'">'])
            ->assertOk()->json('note.title');
        $this->assertStringStartsWith('Drawing · ', $title);
        $this->actingAs($me)->getJson(route('me.takenote.data'))->assertJsonPath('notes.0.lead', 'sketch')->assertJsonPath('notes.0.thumb', $asset);
    }
}

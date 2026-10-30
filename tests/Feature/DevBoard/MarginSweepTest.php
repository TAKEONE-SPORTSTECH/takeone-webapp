<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nothing uploaded outlives its note: the Bin empties itself after 30 days,
 * files and all, and an upload taken out of every note is deleted after a day.
 * What is still in use — or can still be restored — is never touched.
 */
class MarginSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.takenote.store')) {
            $this->markTestSkipped('DEVBOARD_ENABLED / TAKENOTE_ENABLED is off');
        }
        Storage::fake('local');
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function png(): UploadedFile
    {
        $img = imagecreatetruecolor(40, 30);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 50, 90));
        ob_start(); imagepng($img); $bytes = ob_get_clean();

        return UploadedFile::fake()->createWithContent('shot.png', $bytes);
    }

    /** A note with one picture in it; returns [card uuid, asset]. */
    private function noteWithPicture(User $me, string $words): array
    {
        $card = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => '<p>'.$words.'</p>'])->json('note');
        $asset = $this->actingAs($me)->post(route('me.margin.pictures.store', $card['uuid']), ['image' => $this->png()], ['Accept' => 'application/json'])->json('asset.uuid');
        $this->actingAs($me)->patchJson(route('me.takenote.update', $card['uuid']), ['version' => $card['version'], 'html' => '<p>'.$words.'</p><img data-asset="'.$asset.'">'])->assertOk();

        return [$card['uuid'], Asset::where('uuid', $asset)->firstOrFail()];
    }

    public function test_the_bin_empties_itself_after_thirty_days_with_every_file(): void
    {
        $me = $this->owner();
        [$old, $oldPic] = $this->noteWithPicture($me, 'binned long ago');
        [$recent, $recentPic] = $this->noteWithPicture($me, 'binned yesterday');

        $this->actingAs($me)->deleteJson(route('me.takenote.destroy', $old))->assertOk();
        $this->actingAs($me)->deleteJson(route('me.takenote.destroy', $recent))->assertOk();
        Card::withTrashed()->where('uuid', $old)->update(['deleted_at' => now()->subDays(31)]);
        Card::withTrashed()->where('uuid', $recent)->update(['deleted_at' => now()->subDay()]);

        // a dry run deletes nothing
        $this->artisan('devboard:sweep')->assertSuccessful();
        Storage::disk('local')->assertExists($oldPic->path);

        $this->artisan('devboard:sweep --apply')->assertSuccessful();

        $this->assertNull(Card::withTrashed()->where('uuid', $old)->first());
        $this->assertNull(Asset::where('uuid', $oldPic->uuid)->first());
        Storage::disk('local')->assertMissing($oldPic->path);
        Storage::disk('local')->assertMissing($oldPic->preview_path);
        $this->assertSame(0, Item::withTrashed()->whereNotIn('card_id', Card::withTrashed()->pluck('id'))->count());

        // still restorable: untouched
        $this->assertNotNull(Card::withTrashed()->where('uuid', $recent)->first());
        Storage::disk('local')->assertExists($recentPic->path);
    }

    public function test_an_upload_taken_out_of_its_note_is_deleted_and_one_in_use_is_kept(): void
    {
        $me = $this->owner();
        [$uuid, $kept] = $this->noteWithPicture($me, 'keeps its picture');

        // a second picture is added, then taken out of the text again
        $removed = Asset::where('uuid', $this->actingAs($me)->post(route('me.margin.pictures.store', $uuid), ['image' => $this->png()], ['Accept' => 'application/json'])->json('asset.uuid'))->firstOrFail();
        // and a third was uploaded minutes ago and is not in the text YET
        $fresh = Asset::where('uuid', $this->actingAs($me)->post(route('me.margin.pictures.store', $uuid), ['image' => $this->png()], ['Accept' => 'application/json'])->json('asset.uuid'))->firstOrFail();
        // a drawing over the kept picture: the photo underneath stays in use through it
        $marked = $this->actingAs($me)->post(route('me.margin.ink.store', $uuid), [
            'image' => $this->png(), 'kind' => 'markup', 'w' => 40, 'h' => 30, 'base' => $kept->uuid,
            'strokes' => json_encode([['t' => 'pen', 'c' => '#ff3b5c', 'w' => 3, 'p' => [[1, 1], [9, 9]]]]),
        ], ['Accept' => 'application/json'])->json('asset.uuid');
        $note = $this->actingAs($me)->getJson(route('me.takenote.show', $uuid))->json('note');
        $this->actingAs($me)->patchJson(route('me.takenote.update', $uuid), ['version' => $note['version'], 'html' => '<p>keeps its picture</p><img data-asset="'.$marked.'">'])->assertOk();

        Asset::whereIn('uuid', [$kept->uuid, $removed->uuid, $marked])->update(['created_at' => now()->subDays(2)]);

        $this->artisan('devboard:sweep --apply')->assertSuccessful();

        $this->assertNull(Asset::where('uuid', $removed->uuid)->first(), 'taken out of the note: deleted');
        Storage::disk('local')->assertMissing($removed->path);
        $this->assertNotNull(Asset::where('uuid', $marked)->first(), 'in the note: kept');
        $this->assertNotNull(Asset::where('uuid', $kept->uuid)->first(), 'the photo under a drawing in use: kept');
        Storage::disk('local')->assertExists($kept->path);
        $this->assertNotNull(Asset::where('uuid', $fresh->uuid)->first(), 'uploaded minutes ago: kept for a day');
    }
}

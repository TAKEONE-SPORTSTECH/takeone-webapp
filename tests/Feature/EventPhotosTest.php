<?php

namespace Tests\Feature;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventPhoto;
use App\Members\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The gallery's photographs (EVENT_PHOTOS).
 *
 * Adding and deleting is the organiser's; viewing is whoever may read the
 * event, and every other reader gets the same 404. Uploads are re-encoded,
 * so nothing the client sent — metadata included — reaches the disk.
 */
class EventPhotosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['events.photos' => true]);
    }

    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
            'title' => 'Championship',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ], $attrs));
    }

    /** A real JPEG carrying an EXIF block with a marker we can look for afterwards. */
    private function jpeg(int $w = 1200, int $h = 800): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 40, 40));
        ob_start();
        imagejpeg($img, null, 90);
        $bytes = (string) ob_get_clean();

        $payload = "Exif\0\0GPS-SECRET-26.2285N-50.5860E";
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        $bytes = substr($bytes, 0, 2).$app1.substr($bytes, 2);

        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'IMG_0001.jpg', 'image/jpeg', null, true);
    }

    private function upload(User $as, ClubEvent $event, UploadedFile $file)
    {
        return $this->actingAs($as->fresh())->postJson("/me/events/{$event->uuid}/photos", ['photo' => $file]);
    }

    public function test_an_organiser_adds_a_photo_and_it_is_re_encoded_without_metadata(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $res = $this->upload($organiser, $event, $this->jpeg(3000, 2000))->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertNotNull($res->json('photo.delete_url'));

        $photo = EventPhoto::firstOrFail();
        $this->assertStringStartsWith("events/{$event->uuid}/photos/", $photo->path);
        $this->assertStringNotContainsString('IMG_0001', $photo->path);
        $this->assertSame(2400, $photo->width);   // fitted to the display edge
        $this->assertSame(1600, $photo->height);

        $full = Storage::disk('local')->get($photo->path);
        $this->assertStringNotContainsString('GPS-SECRET', $full);
        $this->assertSame([720, 480], array_slice(getimagesizefromstring(Storage::disk('local')->get($photo->thumb_path)), 0, 2));
    }

    public function test_a_file_that_is_not_a_picture_is_refused(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        file_put_contents($path, "\xFF\xD8\xFF\xE0<?php system(\$_GET['c']); ?>");

        $this->upload($organiser, $event, new UploadedFile($path, 'x.jpg', 'image/jpeg', null, true))->assertStatus(422);
        $this->assertSame(0, EventPhoto::count());
    }

    public function test_somebody_who_does_not_run_the_event_cannot_add_or_delete(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $stranger = $this->createUser();

        $this->upload($stranger, $event, $this->jpeg())->assertForbidden();

        $this->upload($organiser, $event, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();

        $this->actingAs($stranger->fresh())->deleteJson("/me/events/{$event->uuid}/photos/{$photo->uuid}")->assertForbidden();
        $this->assertSame(1, EventPhoto::count());
    }

    public function test_deleting_removes_the_files_before_the_row(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->upload($organiser, $event, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();

        $this->actingAs($organiser->fresh())->deleteJson("/me/events/{$event->uuid}/photos/{$photo->uuid}")
            ->assertOk()->assertJson(['uuid' => $photo->uuid]);

        $this->assertSame(0, EventPhoto::count());
        $this->assertFalse(Storage::disk('local')->exists($photo->path));
        $this->assertFalse(Storage::disk('local')->exists($photo->thumb_path));
    }

    public function test_a_private_events_photos_are_only_for_its_readers(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->upload($organiser, $event, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();
        $url = "/me/events/{$event->uuid}/photos/{$photo->uuid}/thumb";

        $this->actingAs($organiser->fresh())->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // A signed-in browser's 404 is redirected home (bootstrap/app.php);
        // the JSON door shows the real answer.
        $this->actingAs($this->createUser())->get($url)->assertRedirect('/');
        $this->actingAs($this->createUser())->getJson($url)->assertNotFound();

        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_a_public_events_photos_open_for_a_visitor(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser, ['entry_mode' => 'public']);
        $this->upload($organiser, $event, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();

        auth()->logout();
        $this->get("/me/events/{$event->uuid}/photos/{$photo->uuid}/full")->assertOk();
    }

    public function test_a_photo_cannot_be_reached_through_another_events_address(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $mine = $this->event($club, $organiser);
        $other = $this->event($club, $organiser, ['title' => 'Other']);
        $this->upload($organiser, $mine, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();

        $this->actingAs($organiser->fresh())->getJson("/me/events/{$other->uuid}/photos/{$photo->uuid}/thumb")->assertNotFound();
        $this->actingAs($organiser->fresh())->deleteJson("/me/events/{$other->uuid}/photos/{$photo->uuid}")->assertNotFound();
    }

    public function test_switched_off_everything_answers_not_found(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        config(['events.photos' => false]);

        $this->upload($organiser, $event, $this->jpeg())->assertNotFound();
    }

    public function test_deleting_the_event_takes_its_photo_files_with_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->upload($organiser, $event, $this->jpeg())->assertOk();
        $photo = EventPhoto::firstOrFail();

        $event->delete();

        $this->assertFalse(Storage::disk('local')->exists($photo->path));
        $this->assertSame(0, EventPhoto::count());
    }
}

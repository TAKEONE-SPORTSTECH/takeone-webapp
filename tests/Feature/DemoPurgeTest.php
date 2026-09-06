<?php

namespace Tests\Feature;

use App\Members\Models\User;
use App\Members\Models\UserPhoto;
use App\Support\DemoManifest;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `demo:purge` must remove exactly what the demo dataset left behind — and no
 * more. The interesting case is a table `demo:seed` never wrote to: a member's
 * extra profile pictures arrive later (from the UI, or a backfill), so they are
 * invisible to the manifest and were being left behind as orphan rows pointing
 * at deleted users, with their files stranded on disk.
 */
class DemoPurgeTest extends TestCase
{
    protected function tearDown(): void
    {
        DemoManifest::delete();
        parent::tearDown();
    }

    /** A manifest recording just these users, as demo:seed would have written it. */
    private function manifestFor(User ...$users): void
    {
        Storage::disk(DemoManifest::DISK)->put(DemoManifest::FILE, json_encode([
            'seeded_at' => now()->toIso8601String(),
            'tables' => ['users' => array_map(fn (User $u) => $u->id, $users)],
            'files' => [],
        ]));
    }

    private function photoFor(User $user): UserPhoto
    {
        $path = "people/{$user->uuid}/profile/".bin2hex(random_bytes(8)).'.png';
        Storage::disk('public')->put($path, 'png-bytes');

        return UserPhoto::create(['user_id' => $user->id, 'path' => $path, 'sort_order' => 0]);
    }

    public function test_it_purges_profile_pictures_owned_by_a_demo_user(): void
    {
        $demo = $this->createUser();
        $photo = $this->photoFor($demo);
        $this->manifestFor($demo);

        $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('user_photos', ['id' => $photo->id]);
        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
        $this->assertFalse(Storage::disk('public')->exists($photo->path), 'the picture file was left orphaned on disk');
    }

    public function test_it_leaves_a_real_members_pictures_alone(): void
    {
        $demo = $this->createUser();
        $real = $this->createUser();
        $realPhoto = $this->photoFor($real);

        $this->manifestFor($demo);                 // only the demo user is recorded

        $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('user_photos', ['id' => $realPhoto->id]);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
        $this->assertTrue(Storage::disk('public')->exists($realPhoto->path));
    }

    public function test_it_reports_the_derived_rows_in_its_own_count(): void
    {
        $demo = $this->createUser();
        $this->photoFor($demo);
        $this->photoFor($demo);
        $this->manifestFor($demo);

        // 1 user + 2 pictures — the confirmation figure must not undercount.
        $this->artisan('demo:purge', ['--force' => true])
            ->expectsOutputToContain('3 demo rows')
            ->assertSuccessful();
    }
}

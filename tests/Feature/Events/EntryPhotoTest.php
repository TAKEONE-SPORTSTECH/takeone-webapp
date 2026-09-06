<?php

namespace Tests\Feature\Events;

use App\Events\Support\EntryPhoto;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Throwing away an entry's photograph must never throw away somebody's face.
 *
 * `club_event_registrations.photo` holds one of two very different things: a
 * picture an organiser took for this competition, or a REFERENCE to the
 * athlete's own `users.profile_picture` (which is how an entry settled at the
 * public door is stored — copying the bytes twice to say the same thing is
 * waste). Every place that dropped an entry photo used to treat it as the
 * first, and on a row of the second kind that deleted the MEMBER's profile
 * picture from disk while `users.profile_picture` and their `user_photos` row
 * carried on naming it: the column survives, the file does not, and the profile
 * renders a broken image with nothing in the logs.
 *
 * It has cost six real pictures across two occasions — four recovered from
 * backup, two gone for good. Hence this test: the guard is the only thing
 * standing between a routine console action and somebody's face.
 *
 * ⚠️ `php artisan config:clear` before running — see CLAUDE.md.
 */
class EntryPhotoTest extends TestCase
{
    use RefreshDatabase;

    /** The case that cost the pictures: the entry photo IS the profile picture. */
    public function test_it_refuses_to_delete_a_file_a_members_profile_still_claims(): void
    {
        Storage::fake('public');

        $path = 'members/'.fake()->uuid().'/profile/'.fake()->uuid().'.jpg';
        Storage::disk('public')->put($path, 'not-really-a-jpeg');

        User::factory()->create(['profile_picture' => $path]);

        EntryPhoto::discard($path);

        Storage::disk('public')->assertExists($path);
        $this->assertTrue(EntryPhoto::isClaimedElsewhere($path));
    }

    /** A picture the competition owns is still the competition's to remove. */
    public function test_it_deletes_a_photo_nothing_else_points_at(): void
    {
        Storage::fake('public');

        $path = 'events/'.fake()->uuid().'/competitors/c12-abcdef.jpg';
        Storage::disk('public')->put($path, 'not-really-a-jpeg');

        EntryPhoto::discard($path);

        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(EntryPhoto::isClaimedElsewhere($path));
    }

    /** A member's gallery row claims the file just as their avatar does. */
    public function test_a_user_photo_row_also_claims_the_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_picture' => null]);
        $path = 'members/'.$user->uuid.'/profile/'.fake()->uuid().'.jpg';
        Storage::disk('public')->put($path, 'not-really-a-jpeg');

        $user->photos()->create(['path' => $path, 'sort_order' => 0, 'uuid' => fake()->uuid()]);

        EntryPhoto::discard($path);

        Storage::disk('public')->assertExists($path);
    }

    /** An empty or absent value is not an error, and deletes nothing. */
    public function test_nothing_to_discard_is_not_a_failure(): void
    {
        Storage::fake('public');

        EntryPhoto::discard(null);
        EntryPhoto::discard('');

        $this->assertTrue(true);
    }
}

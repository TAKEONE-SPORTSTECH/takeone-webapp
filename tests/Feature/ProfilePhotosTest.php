<?php

namespace Tests\Feature;

use App\Members\Models\UserPhoto;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A profile holds several pictures: one is the avatar (mirrored into
 * users.profile_picture), the rest sit behind it.
 *
 * These cover the whole lifecycle plus the two things that must never slip —
 * another member cannot touch your pictures, and a deleted picture takes its
 * file with it.
 */
class ProfilePhotosTest extends TestCase
{
    /** A 1x1 transparent PNG as a base64 data URI (real image bytes). */
    private function validPng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    /** A PHP web shell wearing an image data-URI header. */
    private function disguisedPhp(): string
    {
        return 'data:image/png;base64,'.base64_encode('<?php system($_GET["c"]); ?>');
    }

    public function test_a_member_can_add_several_pictures_and_the_newest_becomes_the_avatar(): void
    {
        $user = $this->createUser();

        $first = $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()]);
        $first->assertOk()->assertJsonPath('success', true);

        $second = $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()]);
        $second->assertOk();

        $this->assertSame(2, $user->photos()->count());

        // The most recent upload is the face of the profile.
        $newest = UserPhoto::orderByDesc('id')->first();
        $this->assertSame($newest->path, $user->fresh()->profile_picture);

        // Stored under the member's own folder, with a server-generated name.
        $this->assertStringStartsWith("members/{$user->uuid}/photos/", $newest->path);
        Storage::disk('public')->assertExists($newest->path);
    }

    public function test_an_older_picture_can_be_promoted_to_avatar(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()])->assertOk();
        $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()])->assertOk();

        $older = $user->photos()->orderBy('id')->first();

        $this->actingAs($user)
            ->putJson("/member/{$user->id}/photos/{$older->uuid}/avatar")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($older->path, $user->fresh()->profile_picture);
    }

    public function test_deleting_the_avatar_promotes_the_next_picture_and_purges_the_file(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()])->assertOk();
        $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()])->assertOk();

        // reorder() first — the relation sorts by sort_order, so a bare orderBy appends.
        $avatar = $user->photos()->reorder()->orderByDesc('id')->first();
        $survivor = $user->photos()->first();
        $this->assertSame($avatar->path, $user->fresh()->profile_picture);

        $this->actingAs($user)
            ->deleteJson("/member/{$user->id}/photos/{$avatar->uuid}")
            ->assertOk()
            ->assertJsonPath('was_avatar', true);

        // File gone before the row, and the profile now points at the survivor.
        Storage::disk('public')->assertMissing($avatar->path);
        $this->assertNull(UserPhoto::find($avatar->id));
        $this->assertSame($survivor->path, $user->fresh()->profile_picture);
    }

    public function test_deleting_the_last_picture_leaves_the_profile_without_an_avatar(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->postJson("/member/{$user->id}/photos", ['image' => $this->validPng()])->assertOk();
        $only = $user->photos()->first();

        $this->actingAs($user)->deleteJson("/member/{$user->id}/photos/{$only->uuid}")->assertOk();

        $this->assertNull($user->fresh()->profile_picture);
        $this->assertSame(0, $user->photos()->count());
    }

    public function test_another_member_cannot_add_delete_or_promote_pictures(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $this->actingAs($owner)->postJson("/member/{$owner->id}/photos", ['image' => $this->validPng()])->assertOk();
        $photo = $owner->photos()->first();

        $this->actingAs($stranger)->postJson("/member/{$owner->id}/photos", ['image' => $this->validPng()])->assertNotFound();
        $this->actingAs($stranger)->putJson("/member/{$owner->id}/photos/{$photo->uuid}/avatar")->assertNotFound();
        $this->actingAs($stranger)->deleteJson("/member/{$owner->id}/photos/{$photo->uuid}")->assertNotFound();

        $this->assertSame(1, $owner->photos()->count());
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_a_picture_uuid_from_another_profile_is_not_found(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();

        $this->actingAs($owner)->postJson("/member/{$owner->id}/photos", ['image' => $this->validPng()])->assertOk();
        $this->actingAs($other)->postJson("/member/{$other->id}/photos", ['image' => $this->validPng()])->assertOk();

        $othersPhoto = $other->photos()->first();

        // A valid uuid, but not one of this member's — scoping must reject it.
        $this->actingAs($owner)
            ->deleteJson("/member/{$owner->id}/photos/{$othersPhoto->uuid}")
            ->assertNotFound();

        $this->assertSame(1, $other->photos()->count());
    }

    public function test_a_php_shell_disguised_as_a_picture_is_rejected(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/photos", ['image' => $this->disguisedPhp()])
            ->assertStatus(422);

        $this->assertSame(0, $user->photos()->count());
        $this->assertNull($user->fresh()->profile_picture);
    }
}

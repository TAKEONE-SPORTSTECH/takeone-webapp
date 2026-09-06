<?php

namespace Tests\Feature;

use App\Members\Models\AffiliationMedia;
use App\Clubs\Models\ClubAffiliation;
use App\Members\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliationMediaUploadTest extends TestCase
{
    private function affiliationFor(User $member): ClubAffiliation
    {
        return ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null,
            'club_name' => 'Dojang', 'start_date' => now()->subYear()->toDateString(),
        ]);
    }

    /** A real 800x600 PNG as a data-URI. */
    private function samplePng(): string
    {
        $im = imagecreatetruecolor(800, 600);
        imagefill($im, 0, 0, imagecolorallocate($im, 40, 120, 200));
        ob_start(); imagepng($im); $bytes = ob_get_clean(); imagedestroy($im);
        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    public function test_uploading_an_image_stores_an_optimized_file(): void
    {
        Storage::fake('public');
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $res = $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media/upload-image", ['image' => $this->samplePng()])
            ->assertOk()->json();

        $this->assertTrue($res['success']);
        $path = $res['path'];
        $this->assertStringContainsString("members/{$member->uuid}/affiliations/{$aff->id}/media/", $path);
        Storage::disk('public')->assertExists($path);

        // Re-encoded (webp when the GD build supports it, else jpg) — never the raw png.
        $this->assertMatchesRegularExpression('/\.(webp|jpg)$/', $path);
    }

    public function test_a_non_image_payload_is_rejected(): void
    {
        Storage::fake('public');
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media/upload-image",
                ['image' => 'data:image/svg+xml;base64,'.base64_encode('<svg onload="alert(1)"></svg>')])
            ->assertStatus(422);
    }

    public function test_saving_an_image_media_requires_a_path_we_stored(): void
    {
        Storage::fake('public');
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        // A made-up path is rejected (can't point at arbitrary/foreign files).
        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media", [
                'media_type' => 'certificate', 'title' => 'Fake', 'media_url' => 'people/someone/secret.webp',
            ])->assertStatus(422);

        // The real flow: upload then save with the returned path.
        $path = $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media/upload-image", ['image' => $this->samplePng()])
            ->json('path');

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media", [
                'media_type' => 'certificate', 'title' => 'Black Belt', 'media_url' => $path,
            ])->assertOk()->assertJsonPath('media.is_image', true);

        $this->assertDatabaseHas('affiliation_media', ['title' => 'Black Belt', 'media_url' => $path]);
    }

    public function test_a_video_media_must_be_a_valid_url(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media", [
                'media_type' => 'video', 'title' => 'x', 'media_url' => 'not a url',
            ])->assertStatus(422);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media", [
                'media_type' => 'video', 'title' => 'Grading', 'media_url' => 'https://youtu.be/abc',
            ])->assertOk();
    }

    public function test_deleting_media_purges_the_uploaded_file(): void
    {
        Storage::fake('public');
        $member = $this->createUser();
        $aff = $this->affiliationFor($member);

        $path = $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media/upload-image", ['image' => $this->samplePng()])
            ->json('path');
        $media = AffiliationMedia::create(['club_affiliation_id' => $aff->id, 'media_type' => 'photo', 'title' => 't', 'media_url' => $path]);
        Storage::disk('public')->assertExists($path);

        $media->delete();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_stranger_cannot_upload(): void
    {
        Storage::fake('public');
        $member = $this->createUser();
        $stranger = $this->createUser();
        $aff = $this->affiliationFor($member);

        $this->actingAs($stranger)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/media/upload-image", ['image' => $this->samplePng()])
            ->assertNotFound();
    }
}

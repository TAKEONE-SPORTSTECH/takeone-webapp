<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Endpoint-level regression tests for the base64 image-upload hardening.
 *
 * These lock in that the profile-picture endpoints reject files whose real
 * bytes are not a whitelisted image — even when the data-URI header lies —
 * so the previous "client-controlled extension" hole cannot reappear.
 */
class UploadSecurityTest extends TestCase
{
    /** A 1x1 transparent PNG as a base64 data URI (real image bytes). */
    private function validPng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    /** A PHP web shell disguised with an image data-URI header. */
    private function disguisedPhp(): string
    {
        return 'data:image/png;base64,' . base64_encode('<?php system($_GET["c"]); ?>');
    }

    public function test_member_upload_rejects_php_disguised_as_image(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson("/member/{$user->id}/upload-picture", [
            'image'    => $this->disguisedPhp(),
            'folder'   => 'avatars',
            'filename' => 'shell',
        ]);

        $response->assertStatus(422);

        // Nothing executable should have been written under any extension.
        foreach (['php', 'phtml', 'png', 'jpg'] as $ext) {
            Storage::disk('public')->assertMissing("avatars/shell.$ext");
        }
        $this->assertNull($user->fresh()->profile_picture);
    }

    public function test_member_upload_accepts_a_real_png(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson("/member/{$user->id}/upload-picture", [
            'image'    => $this->validPng(),
            'folder'   => 'avatars',
            'filename' => 'me',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        // Extension is assigned server-side from the real MIME, not the input.
        $this->assertEquals('avatars/me.png', $user->fresh()->profile_picture);
        Storage::disk('public')->assertExists('avatars/me.png');
    }

    public function test_upload_rejects_traversal_in_folder_or_filename(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson("/member/{$user->id}/upload-picture", [
            'image'    => $this->validPng(),
            'folder'   => '../../public',
            'filename' => 'evil',
        ])->assertStatus(422)->assertJsonValidationErrors('folder');

        $this->actingAs($user)->postJson("/member/{$user->id}/upload-picture", [
            'image'    => $this->validPng(),
            'folder'   => 'avatars',
            'filename' => 'evil.php',
        ])->assertStatus(422)->assertJsonValidationErrors('filename');
    }
}

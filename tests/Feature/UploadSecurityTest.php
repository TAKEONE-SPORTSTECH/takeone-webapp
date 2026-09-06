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
    /**
     * Every upload in this class goes to a FAKE public disk.
     *
     * Without this the suite writes into the real `storage/app/public`, and it
     * has: `avatars/me.png` sat there from a run on 2026-08-05, which is both a
     * stray file nobody owns and a landmine — a later assertion that the path is
     * absent then fails against a leftover from months earlier rather than
     * against anything the test did.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

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

        // A deliberately hostile destination: this is where the caller WANTS the
        // file written. The server must ignore it entirely.
        $response = $this->actingAs($user)->postJson("/member/{$user->id}/upload-picture", [
            'image'    => $this->validPng(),
            'folder'   => 'avatars',
            'filename' => 'me',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $stored = $user->fresh()->profile_picture;

        // The upload succeeds and the extension is assigned server-side from the
        // real MIME, never from the input.
        $this->assertStringEndsWith('.png', $stored);
        Storage::disk('public')->assertExists($stored);

        // …but the DESTINATION is ours. `folder`/`filename` used to be honoured
        // verbatim, which let any authenticated user write to any path on the
        // public disk — naming another member's folder overwrote their picture.
        // The path is now derived from the resolved, authorised owner.
        $this->assertNotEquals('avatars/me.png', $stored, 'the caller must not choose the path');
        $this->assertStringStartsNotWith('avatars/', $stored);
        $this->assertStringContainsString($user->uuid, $stored, 'the owner keys their own folder');
        Storage::disk('public')->assertMissing('avatars/me.png');
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

    // ── Identity documents are private, and reaching one is authorised ──────

    public function test_a_member_can_read_their_own_identity_document(): void
    {
        Storage::fake('local');

        $user = $this->createUser();
        $path = "members/{$user->uuid}/documents/cpr.jpg";
        Storage::disk('local')->put($path, 'not-a-real-jpeg');
        $user->forceFill(['documents' => [['type' => 'CPR', 'file_path' => $path]]])->save();

        $this->actingAs($user)
            ->get(route('member.download-document', ['id' => $user->id, 'path' => $path]))
            ->assertOk();
    }

    public function test_a_stranger_cannot_read_someone_elses_identity_document(): void
    {
        Storage::fake('local');

        $owner = $this->createUser();
        $path = "members/{$owner->uuid}/documents/cpr.jpg";
        Storage::disk('local')->put($path, 'not-a-real-jpeg');

        // A signed-in member with no relationship to the owner. Identity papers
        // are the most sensitive thing on a profile; being logged in is not a
        // reason to be shown someone else's.
        $stranger = $this->createUser();

        $this->actingAs($stranger)
            ->getJson(route('member.download-document', ['id' => $owner->id, 'path' => $path]))
            ->assertNotFound();
    }

    public function test_a_member_cannot_read_a_file_outside_their_own_documents_folder(): void
    {
        Storage::fake('local');

        $user = $this->createUser();
        $other = $this->createUser();
        $victim = "members/{$other->uuid}/documents/cpr.jpg";
        Storage::disk('local')->put($victim, 'not-a-real-jpeg');

        // The path is a request field, so it decides which file is read. Naming
        // another member's document under your OWN id must not work.
        $this->actingAs($user)
            ->getJson(route('member.download-document', ['id' => $user->id, 'path' => $victim]))
            ->assertNotFound();

        // …and neither does climbing out of the folder.
        $this->actingAs($user)
            ->getJson(route('member.download-document', [
                'id' => $user->id,
                'path' => "members/{$user->uuid}/documents/../../../.env",
            ]))
            ->assertNotFound();
    }
}

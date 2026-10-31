<?php

namespace Tests\Feature\Members;

use App\Members\Controllers\CropperUploadController;
use App\Support\StoragePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `POST /image-upload` is answered by our guarded controller, not by the
 * takeone/cropper package (security fix, 2026-10-31).
 *
 * The package's handler took no login, no throttle, and wrote to whatever
 * folder, filename and extension the client named. See docs/uploads.md.
 */
class CropperUploadRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function png(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    public function test_the_route_name_and_url_resolve_to_the_guarded_controller(): void
    {
        $route = Route::getRoutes()->getByName('image.upload');

        $this->assertNotNull($route);
        $this->assertSame('image-upload', $route->uri());
        $this->assertSame(CropperUploadController::class.'@store', ltrim($route->getActionName(), '\\'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('throttle:uploads', $route->gatherMiddleware());
    }

    public function test_a_guest_is_refused_and_nothing_is_written(): void
    {
        $this->postJson('/image-upload', [
            'image' => $this->png(),
            'folder' => 'avatars',
            'filename' => 'planted',
        ])->assertUnauthorized();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_php_disguised_as_an_image_is_rejected(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/image-upload', [
            'image' => 'data:image/png;base64,'.base64_encode('<?php system($_GET["c"]); ?>'),
            'folder' => 'avatars',
            'filename' => 'shell',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_real_image_lands_in_the_uploaders_own_folder_whatever_the_client_asked(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/image-upload', [
            'image' => $this->png(),
            'folder' => 'clubs/someone-else/branding',
            'filename' => 'logo',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $path = $response->json('path');
        $this->assertStringStartsWith(StoragePath::member($user, 'uploads').'/', $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertSame(route('file.show', ['path' => $path]), $response->json('url'));

        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('clubs/someone-else/branding/logo.png');
    }

    public function test_folder_and_filename_are_optional(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/image-upload', ['image' => $this->png()])
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}

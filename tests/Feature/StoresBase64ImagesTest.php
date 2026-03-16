<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies that StoresBase64Images:
 *  - Accepts genuine JPEG / PNG / GIF / WEBP images
 *  - Rejects SVG, PHP, HTML, and any other non-image content
 *  - Always derives the file extension from finfo, never from client input
 *  - Handles malformed base64 and missing payloads safely
 */
class StoresBase64ImagesTest extends TestCase
{
    // Concrete class that exposes the private trait method for testing
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Anonymous class using the trait so we can call the private method
        $this->subject = new class {
            use \App\Traits\StoresBase64Images;

            public function store(string $base64, string $folder, string $name): ?string
            {
                return $this->storeBase64Image($base64, $folder, $name);
            }
        };
    }

    // -------------------------------------------------------------------------
    // Helpers — minimal valid images generated from known-good binaries
    // -------------------------------------------------------------------------

    /** Real 4-byte JPEG (SOI + APP0 header + EOI) */
    private function jpegDataUri(): string
    {
        return 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2Q==';
    }

    /** Real 69-byte 1×1 PNG */
    private function pngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4AWNg+M/AAAAAAgABk+NGAAAAAElFTkSuQmCC';
    }

    /** Real GIF87a 1×1 */
    private function gifDataUri(): string
    {
        return 'data:image/gif;base64,R0lGODdhAQABAAAAAAAA/wAsAAAAAAEAAQAAAgJEAQA7';
    }

    // -------------------------------------------------------------------------
    // Allowed types — should store and return a path
    // -------------------------------------------------------------------------

    public function test_jpeg_is_accepted_and_stored(): void
    {
        $path = $this->subject->store($this->jpegDataUri(), 'images', 'test');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_png_is_accepted_and_stored(): void
    {
        $path = $this->subject->store($this->pngDataUri(), 'images', 'test');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_gif_is_accepted_and_stored(): void
    {
        $path = $this->subject->store($this->gifDataUri(), 'images', 'test');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.gif', $path);
        Storage::disk('public')->assertExists($path);
    }

    // -------------------------------------------------------------------------
    // Extension always comes from finfo, not from the client header
    // -------------------------------------------------------------------------

    public function test_extension_is_always_derived_from_actual_binary_not_header(): void
    {
        // Client claims "image/gif" in the header but sends real PNG bytes
        $realPngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4AWNg+M/AAAAAAgABk+NGAAAAAElFTkSuQmCC');
        $spoofedUri   = 'data:image/gif;base64,' . base64_encode($realPngBytes);

        $path = $this->subject->store($spoofedUri, 'images', 'spoofed');

        // Must be stored as .png (what finfo detected), NOT .gif (what client claimed)
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
    }

    // -------------------------------------------------------------------------
    // Blocked types — must return null and store nothing
    // -------------------------------------------------------------------------

    public function test_php_file_disguised_as_image_is_rejected(): void
    {
        $phpCode = '<?php system($_GET["cmd"]); ?>';
        $uri     = 'data:image/php;base64,' . base64_encode($phpCode);

        $path = $this->subject->store($uri, 'uploads', 'shell');

        $this->assertNull($path);
        Storage::disk('public')->assertDirectoryEmpty('uploads');
    }

    public function test_php_file_with_jpeg_header_claim_is_rejected(): void
    {
        // Attacker claims jpeg but payload is PHP
        $phpCode = '<?php phpinfo(); ?>';
        $uri     = 'data:image/jpeg;base64,' . base64_encode($phpCode);

        $path = $this->subject->store($uri, 'uploads', 'disguised');

        $this->assertNull($path);
    }

    public function test_html_file_is_rejected(): void
    {
        $html = '<html><script>alert(1)</script></html>';
        $uri  = 'data:image/html;base64,' . base64_encode($html);

        $this->assertNull($this->subject->store($uri, 'uploads', 'xss'));
    }

    public function test_svg_is_rejected(): void
    {
        // SVG can carry embedded JS and executes as markup in the browser
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $uri = 'data:image/svg+xml;base64,' . base64_encode($svg);

        $this->assertNull($this->subject->store($uri, 'uploads', 'svg'));
    }

    public function test_plain_text_is_rejected(): void
    {
        $uri = 'data:image/jpeg;base64,' . base64_encode('just a text file');

        $this->assertNull($this->subject->store($uri, 'uploads', 'text'));
    }

    // -------------------------------------------------------------------------
    // Malformed input — must return null safely, never throw
    // -------------------------------------------------------------------------

    public function test_empty_string_is_rejected(): void
    {
        $this->assertNull($this->subject->store('', 'uploads', 'empty'));
    }

    public function test_missing_base64_payload_is_rejected(): void
    {
        $this->assertNull($this->subject->store('data:image/png;base64,', 'uploads', 'nopayload'));
    }

    public function test_invalid_base64_characters_are_rejected(): void
    {
        $uri = 'data:image/png;base64,!!!not-valid-base64!!!';

        $this->assertNull($this->subject->store($uri, 'uploads', 'invalid'));
    }

    public function test_non_data_uri_prefix_is_rejected(): void
    {
        $this->assertNull($this->subject->store('https://evil.com/shell.php', 'uploads', 'url'));
    }

    public function test_no_files_stored_when_input_is_rejected(): void
    {
        $badInputs = [
            'data:image/php;base64,'    . base64_encode('<?php system("ls"); ?>'),
            'data:image/svg+xml;base64,' . base64_encode('<svg><script>x</script></svg>'),
            'data:image/jpeg;base64,!!!INVALID!!!',
            '',
        ];

        foreach ($badInputs as $input) {
            $this->subject->store($input, 'blocked', 'file');
        }

        Storage::disk('public')->assertDirectoryEmpty('blocked');
    }
}

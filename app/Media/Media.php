<?php

namespace App\Media;

use App\Support\Modules\AbstractModule;

/**
 * Footage: the platform's own video pipeline.
 *
 * Owns ingest from a mat camera, the pluggable NAS vaults files are filed into,
 * the HLS transcode ladder, authorised streaming (re-checked per segment), the
 * bout review page and the galleries that list what was filmed.
 *
 * Contributes nothing to the club admin workspace: footage is reached through an
 * event or a member's profile, never as a club setting.
 */
class Media extends AbstractModule
{
    public function key(): string
    {
        return 'media';
    }
}

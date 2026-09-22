<?php

namespace App\Ai;

use App\Support\Modules\AbstractModule;

/**
 * The platform's access to language, image, speech and transcription models.
 *
 * Providers are rows in `ai_providers`, managed at /admin/ai, with their keys
 * encrypted at rest. AiManager picks one per capability and hands back a
 * driver; a caller asks for "text" or "an image" and never names a vendor.
 *
 * ── Why the entry point sits at the module ROOT ───────────────────────────
 *
 * AiManager is used by Translation, by the club Copilot, by the activity
 * catalogue and by two admin screens. Under ModuleBoundaryTest a module's
 * Services/ are PRIVATE, so putting it there would make every one of those
 * callers a violation — and the fix for a violation is never an exception.
 *
 * So this module has no Services/ folder. AiManager, Contracts/ and Drivers/
 * sit at the root, which is the same shape app/Media already uses for
 * MediaVaults, Ffmpeg and BoutFilm: the module's public door is a class you
 * can name, and everything behind it is free to change.
 *
 * ── Why it is not part of Platform ────────────────────────────────────────
 *
 * It is reached from the super-admin screens, which makes it look like
 * platform administration. It is not: Translation calls it on every first read
 * of an event in a new language, and the Copilot calls it for a club. A
 * vertical several modules depend on is infrastructure, and infrastructure
 * with its own table, its own admin surface and its own failover chain is a
 * module.
 */
class Ai extends AbstractModule
{
    public function key(): string
    {
        return 'ai';
    }
}

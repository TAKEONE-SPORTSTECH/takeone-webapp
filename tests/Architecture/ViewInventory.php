<?php

namespace Tests\Architecture;

use Illuminate\Support\Facades\View;

/**
 * Enumerates what the application's view layer actually answers to.
 *
 * This exists because a moved Blade file does not fail loudly. Both
 * App\Support\ClubView::pick() and PersonalEventController::packageView()
 * choose a view with View::exists() and fall back to a desktop/index file
 * when the preferred one is missing — so a bad move renders the WRONG
 * SCREEN rather than throwing. Nothing in a normal smoke test catches that.
 *
 * @see \App\Support\ClubView
 */
class ViewInventory
{
    /**
     * Every (section, module) pair passed to ClubView::pick() in app code.
     *
     * Kept as data rather than scraped, so adding a call site without adding
     * it here is a deliberate omission rather than an accident of regex.
     */
    public const CLUB_SECTIONS = [
        ['shop', 'shop'],
        ['analytics', 'clubs'],
        ['dashboard', 'clubs'],
        ['details', 'clubs'],
        ['gallery', 'clubs'],
        ['financials', 'clubs'],
        ['achievements', 'clubs'],
        ['achievements.verifications', 'clubs'],
        ['notifications', 'clubs'],
        ['instructors', 'clubs'],
        ['activities', 'clubs'],
        ['members', 'clubs'],
        ['packages', 'clubs'],
        ['messages', 'clubs'],
        ['perks', 'shop'],
        ['orders', 'shop'],
        ['events', 'clubs'],
        ['timeline', 'clubs'],
        ['facilities', 'clubs'],
        ['roles', 'clubs'],
    ];

    /** Resolve one club-admin section the way a real request would. */
    public static function pick(string $section, string $module, bool $isMobile): string
    {
        request()->attributes->set('is_mobile', $isMobile);

        return \App\Support\ClubView::pick($section, $module);
    }

    /** Every view name reachable from a blade file on disk. */
    public static function allNames(): array
    {
        $finder = View::getFinder();
        $names = [];

        $scan = function (string $dir, string $ns = '') use (&$names) {
            if (! is_dir($dir)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                    continue;
                }
                $rel = substr($f->getPathname(), strlen(rtrim($dir, '/')) + 1);
                $name = str_replace('/', '.', substr($rel, 0, -strlen('.blade.php')));
                $names[$ns ? "{$ns}::{$name}" : $name] = true;
            }
        };

        foreach ($finder->getPaths() as $p) {
            $scan($p);
        }
        foreach ($finder->getHints() as $ns => $paths) {
            foreach ($paths as $p) {
                $scan($p, $ns);
            }
        }

        ksort($names);

        return array_keys($names);
    }
}

<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\View;
use Tests\Architecture\ViewInventory;
use Tests\TestCase;

/**
 * Guards the view layer against a move that breaks a screen SILENTLY.
 *
 * The danger is not a missing view — that throws. It is ClubView::pick()
 * and packageView(), which ask View::exists() and quietly serve the desktop
 * file when the mobile one has gone. A phone then renders a desktop screen
 * and no error is logged anywhere.
 */
class ViewResolutionTest extends TestCase
{
    /**
     * The club-admin sections that have a real mobile view today.
     *
     * If a reorganisation drops one of these files, ClubView::pick() keeps
     * returning a view that EXISTS (the desktop one) and every other check
     * passes. This list is the only thing that notices.
     */
    private const HAS_MOBILE_VIEW = [
        'clubs::achievements', 'clubs::activities', 'clubs::analytics',
        'clubs::dashboard', 'clubs::details', 'clubs::events',
        'clubs::facilities', 'clubs::financials', 'clubs::gallery',
        'clubs::instructors', 'clubs::members', 'clubs::messages',
        'clubs::notifications', 'clubs::packages', 'clubs::roles',
        'clubs::timeline', 'shop::orders', 'shop::perks', 'shop::shop',
    ];

    /** Sections that legitimately serve desktop on a phone (no mobile file). */
    private const DESKTOP_ONLY = ['clubs::achievements.verifications'];

    public function test_every_club_admin_section_resolves_on_both_devices(): void
    {
        foreach (ViewInventory::CLUB_SECTIONS as [$section, $module]) {
            foreach ([false, true] as $isMobile) {
                $picked = ViewInventory::pick($section, $module, $isMobile);

                $this->assertTrue(
                    View::exists($picked),
                    sprintf(
                        'ClubView::pick(%s, %s) on %s resolved to "%s", which does not exist.',
                        $section, $module, $isMobile ? 'mobile' : 'desktop', $picked
                    )
                );
            }
        }
    }

    public function test_mobile_sections_still_serve_their_mobile_view(): void
    {
        foreach (ViewInventory::CLUB_SECTIONS as [$section, $module]) {
            $key = "{$module}::{$section}";
            $picked = ViewInventory::pick($section, $module, true);
            $servesMobile = str_ends_with($picked, '.mobile');

            if (in_array($key, self::HAS_MOBILE_VIEW, true)) {
                $this->assertTrue($servesMobile, sprintf(
                    'SILENT REGRESSION: "%s" used to serve a mobile view and now serves "%s". '
                    .'Its .mobile blade has probably been moved or renamed — phone users are '
                    .'being shown the desktop screen and nothing else will report it.',
                    $key, $picked
                ));

                continue;
            }

            $this->assertContains($key, self::DESKTOP_ONLY, sprintf(
                '"%s" now serves a mobile view but is not recorded as having one. '
                .'If that is intended, add it to HAS_MOBILE_VIEW.', $key
            ));
        }
    }

    public function test_every_static_view_reference_in_php_resolves(): void
    {
        $missing = [];

        foreach ($this->phpFiles() as $file) {
            $code = file_get_contents($file);

            // Only single-quoted literals: anything interpolated is dynamic and
            // is covered by the ClubView / device-pair tests above instead.
            // The trailing [,)] is essential: it requires the literal to be the
            // WHOLE argument. Without it, view('personal.'.$device.'.event-bout')
            // reports a phantom view named "personal.".
            preg_match_all("/\bview\(\s*'([a-zA-Z0-9_.:\-]+)'\s*[,)]/", $code, $m);

            foreach ($m[1] as $name) {
                if (in_array($name, self::KNOWN_DEAD_REFERENCES, true)) {
                    continue;
                }

                if (! View::exists($name)) {
                    $missing[] = $name.'  <- '.str_replace(base_path().'/', '', $file);
                }
            }
        }

        $this->assertSame([], array_unique($missing), "Unresolvable view() literals:\n".implode("\n", array_unique($missing)));
    }

    public function test_every_extends_and_include_literal_resolves(): void
    {
        $missing = [];

        foreach (ViewInventory::allNames() as $name) {
            if ($this->isVendorView($name)) {
                continue;
            }

            $path = View::getFinder()->find($name);
            $code = file_get_contents($path);

            preg_match_all("/@(?:extends|include|includeIf|includeWhen|includeFirst)\(\s*'([a-zA-Z0-9_.:\-]+)'\s*[,)]/", $code, $m);

            foreach ($m[1] as $ref) {
                if (! View::exists($ref)) {
                    $missing[] = $ref.'  <- '.$name;
                }
            }
        }

        $this->assertSame([], array_unique($missing), "Unresolvable @extends/@include literals:\n".implode("\n", array_unique($missing)));
    }

    /**
     * View names referenced by code that is already dead.
     *
     * These six PersonalMobileController methods (events, eventShow,
     * eventBracket, challenge, duelShow, challengeHistory) have NO route —
     * `route:list` matches zero. They point at flat view names that the
     * mobile/desktop split replaced, and were never updated because nothing
     * ever calls them. Registered in Documentation/HOUSE-CLEANING.md rather
     * than deleted in passing.
     */
    private const KNOWN_DEAD_REFERENCES = [
        'personal.events',
        'personal.event-show',
        'personal.event-bracket',
        'challenges::challenge',
        'challenges::duel-show',
        'challenges::challenge-history',
    ];

    /** Namespaces owned by a package, not by this project. */
    private function isVendorView(string $name): bool
    {
        foreach (['laravel-exceptions', 'laravel-exceptions-renderer', 'horizon', 'pagination', 'notifications', 'mcp'] as $ns) {
            if (str_starts_with($name, $ns.'::')) {
                return true;
            }
        }

        return false;
    }

    /** @return iterable<string> */
    private function phpFiles(): iterable
    {
        foreach (['app', 'routes'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    yield $f->getPathname();
                }
            }
        }
    }
}

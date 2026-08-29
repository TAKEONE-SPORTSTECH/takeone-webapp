<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Uploads must land under the entity that owns them.
 *
 * The root cause of the scattered storage this guards against was not a
 * disagreement about the layout — `App\Support\StoragePath` already described it
 * and CLAUDE.md already documented it. It was that the helper was a CONVENTION:
 * eleven of its methods existed and were called zero times, while call sites
 * hand-built the same folders as string literals and drifted apart.
 *
 * `clubs/logos` (purpose first, owner second), `users/{numeric-id}` (a fourth
 * avatar root), `chat-attachments/{conversation-id}` (keyed on a join table that
 * owns nothing) and `documents/{id}` on the PUBLIC disk all arrived that way.
 *
 * So this test makes the convention enforceable: a new upload written to a flat
 * literal root fails here rather than being noticed months later by someone
 * looking at a disk. It reads source text — no framework, no database.
 */
class StoragePathConventionTest extends TestCase
{
    /** Roots that were removed, and must not come back. */
    private const FORBIDDEN_ROOTS = [
        'clubs/logos', 'clubs/covers', 'clubs/splash',   // purpose-first inversion
        'user-posts/', 'duel-media/', 'business-logos',  // owner-less flat roots
        'club-products/', 'timeline/',
        'chat-attachments/',                             // keyed on the conversation
        "'people/",                                      // second name for members/
        'packages\'',                                    // flat, not under the club
    ];

    /** The calls that put bytes on disk. */
    private const WRITE_CALLS = ['storeBase64Image(', '->store(', '->storeAs(', '->putFileAs('];

    /** @return list<string> every PHP file under app/ */
    private function sources(): array
    {
        $root = dirname(__DIR__, 3).'/app';
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_no_upload_is_written_to_a_removed_flat_root(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            foreach (explode("\n", (string) file_get_contents($path)) as $n => $line) {
                // Comments describe the old roots on purpose — they are history,
                // not behaviour.
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                $writes = false;
                foreach (self::WRITE_CALLS as $call) {
                    $writes = $writes || str_contains($line, $call);
                }
                if (! $writes) {
                    continue;
                }

                // A line that goes through StoragePath is compliant by
                // definition — the literal on it is a PURPOSE argument
                // (`StoragePath::club($club, 'packages')`), not a root.
                if (str_contains($line, 'StoragePath::')) {
                    continue;
                }

                foreach (self::FORBIDDEN_ROOTS as $root) {
                    if (str_contains($line, "'".$root) || str_contains($line, '"'.$root)) {
                        $offenders[] = basename($path).':'.($n + 1).'  '.trim($line);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Uploads must go under their owning entity via App\\Support\\StoragePath.\n"
            ."These write to a flat root that was deliberately removed:\n  "
            .implode("\n  ", $offenders)."\n",
        );
    }

    public function test_storage_path_still_offers_a_method_for_each_owner(): void
    {
        // If one of these disappears, a call site somewhere quietly falls back to
        // a hand-built literal — which is exactly how the drift started.
        foreach ([
            'member', 'memberProfile', 'memberDocuments', 'memberPayments',
            'memberPosts', 'memberChat', 'memberPhotos', 'memberCertifications',
            'memberAchievement', 'memberAffiliationMedia',
            'club', 'clubBranding', 'clubGallery', 'clubBySlug',
            'business', 'event', 'duel',
        ] as $method) {
            $this->assertTrue(
                method_exists(\App\Support\StoragePath::class, $method),
                "StoragePath::{$method}() is relied on by the upload paths",
            );
        }
    }
}

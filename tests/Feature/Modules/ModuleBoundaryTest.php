<?php

namespace Tests\Feature\Modules;

use App\Support\Modules\ModuleRegistry;
use Tests\TestCase;

/**
 * Architectural fitness: a module's INTERNALS stay inside it.
 *
 * The reason to organise the codebase by vertical rather than by technical kind
 * is so that work on one vertical cannot silently sprawl into another. That only
 * holds if the boundary is enforced — otherwise the folders are just tidier
 * names for the same tangle, and the first time something reaches across, the
 * boundary is gone and nobody notices.
 *
 * The rule, and the reasoning:
 *
 *   Models/     PUBLIC. A club has orders; an event has competitors. Verticals
 *               legitimately relate to each other's records, and pretending
 *               otherwise would mean a duplicate read-model for every crossing.
 *
 *   Controllers/  PRIVATE. A controller is an HTTP entry point. Something
 *   Commands/     outside the module calling one is either routing (which now
 *                 lives in the module's own routes files) or bypassing the
 *                 module's own front door. Both are defects.
 *
 *   Services/   PRIVATE by default. A module's domain logic is its own. When
 *               another vertical genuinely needs it, the answer is a named,
 *               documented entry point — not a reach into the internals.
 *
 * If this test fails, the fix is almost never to add an exception. It is either
 * to move the caller into the module, or to give the module an explicit public
 * entry point and call THAT.
 */
class ModuleBoundaryTest extends TestCase
{
    /** Sub-namespaces no other module may reference. */
    private const PRIVATE_LAYERS = ['Controllers', 'Commands', 'Services'];

    public function test_no_module_reaches_into_another_modules_internals(): void
    {
        $moduleRoots = $this->moduleRoots();

        $this->assertNotEmpty($moduleRoots, 'No modules were discovered.');

        $violations = [];

        foreach ($this->phpFilesUnder(base_path()) as $file) {
            $relative = str_replace(base_path().'/', '', $file);
            $source = file_get_contents($file);

            foreach ($moduleRoots as $namespace => $dir) {
                // A file inside the module may of course use its own internals.
                if (str_starts_with($relative, $dir.'/')) {
                    continue;
                }

                foreach (self::PRIVATE_LAYERS as $layer) {
                    /*
                     * The layer may sit at ANY depth inside the module, not only
                     * directly under its root. App\Scoreboard keeps its
                     * controllers at Sports\<Sport>\Controllers\ because a mat
                     * is organised by sport, and a rule that only looked one
                     * level down would have called that boundary enforced while
                     * enforcing nothing.
                     */
                    $pattern = '/'.preg_quote($namespace, '/').'\\\\(?:[A-Za-z0-9_]+\\\\)*'.$layer.'\\\\/';

                    if (preg_match($pattern, $source, $m)) {
                        $violations[] = "{$relative} references {$m[0]}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge(
            ['A module\'s internals are being used from outside it:', ''],
            $violations,
            ['', 'Move the caller into the module, or give the module a public entry point and call that.'],
        )));
    }

    /**
     * Every module's namespace => its directory, derived from the registry so a
     * newly registered module is covered without editing this test.
     *
     * @return array<string, string>
     */
    private function moduleRoots(): array
    {
        $roots = [];

        foreach (app(ModuleRegistry::class)->all() as $module) {
            $reflection = new \ReflectionClass($module);
            $dir = dirname($reflection->getFileName());

            $roots[$reflection->getNamespaceName()] = str_replace(base_path().'/', '', $dir);
        }

        return $roots;
    }

    /** @return iterable<string> */
    private function phpFilesUnder(string $dir): iterable
    {
        $skip = ['vendor', 'node_modules', 'storage', 'public', 'bootstrap/cache', '.git', 'flutter', 'drafts', 'docker'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) use ($dir, $skip) {
                    $rel = str_replace($dir.'/', '', $current->getPathname());

                    foreach ($skip as $s) {
                        if ($rel === $s || str_starts_with($rel, $s.'/')) {
                            return false;
                        }
                    }

                    // A directory this process cannot read must never crash the
                    // guard. Before this, an unreadable container-owned folder
                    // threw from RecursiveDirectoryIterator and the whole
                    // boundary check errored out instead of checking anything.
                    if ($current->isDir() && ! $current->isReadable()) {
                        return false;
                    }

                    return true;
                }
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                yield $file->getPathname();
            }
        }
    }
}

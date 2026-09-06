<?php

namespace Tests\Feature\Modules;

use Tests\TestCase;

/**
 * Architectural fitness: every class name written in the source resolves.
 *
 * This guards the SILENT half of moving a class between module folders. A file
 * that says `hasMany(ClubProduct::class)` with no import resolved fine while the
 * two classes were siblings; move one into a module and the bare name now points
 * at a namespace it does not live in. PHP raises nothing at parse time, nothing
 * at boot, and nothing until that one line runs — which, for a relation used on
 * a single screen, can be weeks. CLAUDE.md calls this out under "Moving a class
 * between modules"; it has already bitten twice.
 *
 * The check is deliberately narrow: only positions where PHP will genuinely try
 * to resolve a class — `X::`, `new X`, `instanceof X`, a parameter type and a
 * return type. Docblocks are not checked, because a wrong @var is a lint
 * problem, not a runtime one.
 *
 * If this fails, the fix is an explicit `use` for the class's new home — never
 * deleting the reference or the check.
 */
class ClassReferenceTest extends TestCase
{
    /** Directories whose PHP is ours to keep honest. */
    private const ROOTS = ['app', 'database', 'tests', 'routes', 'config'];

    /** Reserved words and pseudo-types a T_STRING can legitimately be. */
    private const NOT_A_CLASS = [
        'self', 'static', 'parent', 'class', 'true', 'false', 'null', 'int', 'string',
        'bool', 'float', 'array', 'void', 'mixed', 'object', 'callable', 'iterable',
        'never', 'fn', 'function', 'new', 'use', 'namespace', 'list', 'default',
        'match', 'print', 'echo', 'exit', 'die', 'and', 'or', 'xor',
    ];

    public function test_every_class_reference_resolves_to_a_real_class(): void
    {
        $unresolvable = [];

        foreach (self::ROOTS as $root) {
            foreach ($this->phpFilesUnder(base_path($root)) as $file) {
                foreach ($this->unresolvableIn($file) as $problem) {
                    $unresolvable[] = $problem;
                }
            }
        }

        $this->assertSame([], $unresolvable, implode("\n", array_merge(
            ['Class references that do not resolve:', ''],
            $unresolvable,
            ['', 'Add an explicit `use` for the class\'s current namespace.'],
        )));
    }

    /** @return list<string> */
    private function unresolvableIn(string $path): array
    {
        $tokens = @token_get_all(file_get_contents($path));

        if (! $tokens) {
            return [];
        }

        [$namespace, $imports] = $this->contextOf($tokens);

        $relative = str_replace(base_path().'/', '', $path);
        $problems = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $name = $token[1];

            if (in_array(strtolower($name), self::NOT_A_CLASS, true)) {
                continue;
            }

            $previous = $this->neighbour($tokens, $i, -1);
            $next = $this->neighbour($tokens, $i, 1);

            // A method call, a property, a declaration, or part of a longer name.
            if (is_array($previous) && in_array($previous[0], [
                T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION,
                T_CONST, T_NS_SEPARATOR, T_STRING, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_GOTO,
            ], true)) {
                continue;
            }

            $isClassPosition =
                (is_array($next) && $next[0] === T_DOUBLE_COLON)                    // X::…
                || (is_array($previous) && in_array($previous[0], [T_NEW, T_INSTANCEOF], true))
                || (is_array($next) && $next[0] === T_VARIABLE)                     // X $y  /  ?X $y  /  A|X $y
                || (($previous === ':' || $previous === '?') && ($next === '{' || $next === ';'));

            if (! $isClassPosition) {
                continue;
            }

            $resolved = $imports[strtolower($name)] ?? ($namespace ? $namespace.'\\'.$name : $name);

            if ($this->exists($resolved) || $this->exists($name)) {
                continue;
            }

            $problems[] = "{$relative}:{$token[2]}  {$name} → {$resolved}";
        }

        return array_values(array_unique($problems));
    }

    /**
     * The file's namespace and its import map (alias => fully-qualified name).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function contextOf(array $tokens): array
    {
        $nameParts = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_NS_SEPARATOR];
        $namespace = '';
        $imports = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {
                    $part = $tokens[$j];

                    if (is_array($part) && $part[0] === T_WHITESPACE) {
                        continue;
                    }
                    if (is_array($part) && in_array($part[0], $nameParts, true)) {
                        $namespace .= $part[1];

                        continue;
                    }
                    break;
                }

                $namespace = trim($namespace, '\\');
            }

            if ($token[0] !== T_USE) {
                continue;
            }

            // Only a plain top-level import. A trait `use` inside a class body, a
            // closure's `use (…)`, and `use function`/`use const` all bail out here.
            $buffer = '';
            $plain = true;

            for ($j = $i + 1; $j < $count; $j++) {
                $part = $tokens[$j];

                if (is_array($part) && $part[0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($part) && in_array($part[0], $nameParts, true)) {
                    $buffer .= $part[1];

                    continue;
                }
                if (is_array($part) && $part[0] === T_AS) {
                    $buffer .= '|as|';

                    continue;
                }
                if ($part === ';') {
                    break;
                }

                $plain = false;
                break;
            }

            if (! $plain || $buffer === '') {
                continue;
            }

            foreach (explode(',', $buffer) as $one) {
                if (str_contains($one, '|as|')) {
                    [$fqn, $alias] = explode('|as|', $one);
                } else {
                    $fqn = $one;
                    $alias = substr(strrchr('\\'.$one, '\\'), 1);
                }

                $imports[strtolower($alias)] = trim($fqn, '\\');
            }
        }

        return [$namespace, $imports];
    }

    /** The nearest non-whitespace token in the given direction. */
    private function neighbour(array $tokens, int $from, int $step): mixed
    {
        for ($i = $from + $step; isset($tokens[$i]); $i += $step) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    private function exists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
    }

    /** @return iterable<string> */
    private function phpFilesUnder(string $dir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                fn (\SplFileInfo $current) => ! $current->isDir() || $current->isReadable()
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}

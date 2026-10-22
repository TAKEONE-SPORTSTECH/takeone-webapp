<?php

namespace App\Printables\Support;

use App\Printables\Contracts\PrintableKind;

/** The registered printable kinds — config('printables.kinds'). */
final class Kinds
{
    /** @var array<string, PrintableKind>|null */
    private ?array $kinds = null;

    /** @return array<string, PrintableKind> */
    public function all(): array
    {
        if ($this->kinds === null) {
            $this->kinds = [];
            foreach ((array) config('printables.kinds', []) as $key => $class) {
                if (is_string($key) && is_string($class) && class_exists($class)) {
                    $kind = app($class);
                    if ($kind instanceof PrintableKind && $kind->key() === $key) {
                        $this->kinds[$key] = $kind;
                    }
                }
            }
        }

        return $this->kinds;
    }

    public function get(?string $key): ?PrintableKind
    {
        return $key !== null ? ($this->all()[$key] ?? null) : null;
    }
}

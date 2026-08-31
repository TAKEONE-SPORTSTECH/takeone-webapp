<?php

namespace App\Events\Support\Tournament;

/**
 * The one seam a shared tournament collaborator needs to speak in its
 * package's own voice.
 *
 * Everything in app/Events/Support/Tournament/ is sport-agnostic: it must
 * never know which sport it is serving, and must never carry a `match` on a
 * sport key. The strings it emits, though, belong to the package — a mat, a
 * call to the ring, a division gate are all worded by the sport's own
 * language files. So the package supplies its lang namespace and the base
 * asks for a key inside it, never the other way round.
 */
trait SpeaksPackageLanguage
{
    /**
     * The event package's Blade/lang namespace, e.g. `event-bjj_tournament`.
     * Supplied by the concrete subclass inside the package.
     */
    abstract protected function langNamespace(): string;

    /**
     * A translated string from the package's own messages file.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function t(string $key, array $replace = []): string
    {
        return (string) __($this->langNamespace().'::messages.'.$key, $replace);
    }
}

<?php

namespace App\Support\Modules;

/**
 * Defaults for a platform module, so an implementation only states what is
 * actually particular to it.
 */
abstract class AbstractModule implements Module
{
    /** This module's view / translation namespace: `shop::…`. */
    public function namespace(): string
    {
        return $this->key();
    }

    /**
     * Translate a key from the module's own language files.
     *
     * Strings live at the level that owns them, exactly as in the event
     * packages: a string only this module uses belongs to this module, and one
     * the whole platform shares stays in lang/{en,ar}/.
     */
    protected function line(string $key, array $replace = []): string
    {
        return __($this->namespace().'::messages.'.$key, $replace);
    }
}

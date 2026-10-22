<?php

namespace App\Printables;

use App\Support\Modules\AbstractModule;

/**
 * Printables: ONE designer for everything the platform prints (2026-10-19).
 *
 * A certificate, an official's badge, a poster, an ID card — each is a KIND
 * (App\Printables\Contracts\PrintableKind) supplied by the module that owns
 * the reason for printing it. This module owns only the canvas: the document
 * model, the page sizes, the fonts, the uploaded pictures and the editor.
 *
 * Other modules reach it through App\Printables\Designer — never through its
 * controllers or support classes.
 *
 * Switched by config('printables.enabled') — off by default.
 */
class Printables extends AbstractModule
{
    public function key(): string
    {
        return 'printables';
    }
}

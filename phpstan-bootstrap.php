<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap shim.
 *
 * larastan's LarastanStubFilesExtension reads Larastan\Larastan\LARAVEL_VERSION
 * while the PHPStan container is being built. That constant is only defined as a
 * side effect of larastan's own bootstrap.php successfully booting the Laravel
 * app — so any hiccup in that boot (it is intermittent here) leaves the constant
 * undefined and the whole analysis dies before it reads a single file, with a
 * message that points nowhere near the real cause.
 *
 * Define both spellings up front from the framework's own constant, which needs
 * no application boot at all. larastan's bootstrap still runs afterwards for the
 * rest of its setup; its own `if (! defined(...))` guard means this never
 * conflicts. Cheap insurance against a failure mode that otherwise looks like a
 * code error.
 */

require_once __DIR__.'/vendor/autoload.php';

if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', \Illuminate\Foundation\Application::VERSION);
}

if (! defined('Larastan\Larastan\LARAVEL_VERSION')) {
    define('Larastan\Larastan\LARAVEL_VERSION', LARAVEL_VERSION);
}

require_once __DIR__.'/vendor/larastan/larastan/bootstrap.php';

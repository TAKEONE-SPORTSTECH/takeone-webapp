<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap shim.
 *
 * larastan's own bootstrap.php boots the app and defines the GLOBAL constant
 * LARAVEL_VERSION, but LarastanStubFilesExtension resolves the NAMESPACED
 * Larastan\Larastan\LARAVEL_VERSION while the container is being built — before
 * PHP's global-constant fallback can help it. With a warm result cache the
 * extension is never constructed and the mismatch stays hidden, so this only
 * surfaces on a cold run (e.g. straight after `composer dump-autoload`).
 *
 * Defining the namespaced alias alongside the global one costs nothing and keeps
 * `composer analyse` deterministic instead of cache-dependent.
 */

require_once __DIR__.'/vendor/larastan/larastan/bootstrap.php';

if (defined('LARAVEL_VERSION') && ! defined('Larastan\Larastan\LARAVEL_VERSION')) {
    define('Larastan\Larastan\LARAVEL_VERSION', LARAVEL_VERSION);
}

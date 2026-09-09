<?php

declare(strict_types=1);

/**
 * Hand-rolled PSR-4 autoloader for the Cuniform\ namespace, rooted at this directory.
 * No Composer autoload at runtime (SPEC §4.2) — bin/cuniform requires this file directly.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cuniform\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// Template escaping helpers (e/eAttr/eUrl/eJs) are global functions, not
// classes — the autoloader above can't reach them. Templates call them bare
// (SPEC §9), so they must be available wherever a template can run.
require_once __DIR__ . '/Template/helpers.php';

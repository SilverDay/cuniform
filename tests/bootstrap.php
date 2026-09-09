<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

/**
 * Same hand-rolled PSR-4 pattern as src/autoload.php, for the Cuniform\Tests\ namespace.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Cuniform\\Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

<?php

declare(strict_types=1);

$dirs = array_filter(
    [__DIR__ . '/src', __DIR__ . '/bin', __DIR__ . '/tests', __DIR__ . '/templates', __DIR__ . '/admin'],
    static fn (string $dir): bool => is_dir($dir),
);

$finder = (new PhpCsFixer\Finder())
    ->in($dirs)
    ->exclude('Vendor')
    ->append(is_file(__DIR__ . '/bin/cuniform') ? [__DIR__ . '/bin/cuniform'] : []);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);

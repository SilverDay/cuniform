<?php

declare(strict_types=1);

/**
 * Fails if a template echoes anything that is not routed through an escaping helper.
 * Exactly one unescaped sink is permitted: $doc->bodyHtml (SPEC §9, rule 2).
 */
$root = $argv[1] ?? 'templates';

if (!is_dir($root)) {
    echo "escaping-lint: {$root} does not exist yet, skipping\n";
    exit(0);
}

$allowed = '/^\s*(e|eAttr|eUrl|eJs|t)\s*\(/';
$sink    = '/^\s*\$doc->bodyHtml\s*$/';
$errors  = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $lines = file($f->getPathname(), FILE_IGNORE_NEW_LINES);
    foreach ($lines as $i => $line) {
        if (!preg_match_all('/<\?=(.+?)\?>/', $line, $m)) {
            continue;
        }
        foreach ($m[1] as $expr) {
            if (preg_match($allowed, $expr) || preg_match($sink, $expr)) {
                continue;
            }
            $errors[] = sprintf('%s:%d: unescaped output: <?=%s?>', $f->getPathname(), $i + 1, $expr);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    fwrite(STDERR, sprintf("escaping-lint: %d violation(s)\n", count($errors)));
    exit(1);
}

echo "escaping-lint: ok\n";

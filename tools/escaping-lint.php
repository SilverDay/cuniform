<?php

declare(strict_types=1);

/**
 * Fails if a template echoes anything that is not routed through an escaping helper.
 * $doc->bodyHtml is the one unescaped sink SPEC §9 names directly — renderer output.
 * Two more are enumerated here, both already-safe HTML by construction rather than
 * unescaped user input: $layout->content (another template's own output, already
 * escaped except for its own bodyHtml sink) and a heading's 'text' entry (already
 * HTML-escaped and inline-formatted by the renderer's getHeadings(), same as
 * bodyHtml — see Md2Html::parseInline()). Any other bare expression must go through
 * a helper.
 */
$root = $argv[1] ?? 'templates';

if (!is_dir($root)) {
    echo "escaping-lint: {$root} does not exist yet, skipping\n";
    exit(0);
}

$allowed = '/^\s*(e|eAttr|eUrl|eJs|t)\s*\(/';
$sink    = '/^\s*(\$doc->bodyHtml|\$layout->content|\$\w+\[[\'"]text[\'"]\])\s*$/';
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

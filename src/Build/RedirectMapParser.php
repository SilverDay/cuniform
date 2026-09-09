<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\ContentException;

/**
 * Reads `content/redirects.map`'s manual entries (SPEC §8.3): one
 * `old-path new-path` pair per line, whitespace-separated, both sides
 * site-relative paths starting with `/`. Blank lines and `#` comment lines
 * are ignored — matching Apache's own `RewriteMap txt:` file format, which
 * SPEC names as one compilation target, so a hand-edited map already looks
 * like what it might eventually feed directly.
 *
 * The file is optional: a fresh site with nothing to redirect yet has no
 * reason to carry an empty file, so a missing file parses as zero entries
 * rather than an error.
 */
final class RedirectMapParser
{
    /**
     * @return list<RedirectEntry>
     *
     * @throws ContentException When any non-blank, non-comment line isn't
     *                          exactly two whitespace-separated site-relative
     *                          paths — every bad line is reported together.
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw ContentException::redirectMapInvalid($path, ['file could not be read']);
        }

        $entries = [];
        $errors  = [];

        foreach (explode("\n", $contents) as $lineNumber => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $columns = preg_split('/\s+/', $trimmed);
            if ($columns === false || count($columns) !== 2) {
                $errors[] = sprintf('line %d: expected "old-path new-path", got: %s', $lineNumber + 1, $trimmed);

                continue;
            }

            [$oldPath, $newPath] = $columns;
            if (!str_starts_with($oldPath, '/') || !str_starts_with($newPath, '/')) {
                $errors[] = sprintf('line %d: both paths must start with "/": %s', $lineNumber + 1, $trimmed);

                continue;
            }

            $entries[] = new RedirectEntry($oldPath, $newPath, 'content/redirects.map');
        }

        if ($errors !== []) {
            throw ContentException::redirectMapInvalid($path, $errors);
        }

        return $entries;
    }
}

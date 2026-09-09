<?php

declare(strict_types=1);

namespace Cuniform\Content;

/**
 * Derives a page's route-relative path (SPEC §6.3) from where its file sits
 * in the tree, e.g. `pages/de/vortraege/index.md` -> `vortraege`,
 * `pages/en/talks/coffee-factor.md` -> `talks/coffee-factor` — matching
 * SPEC's own examples exactly, both of which happen to show a filename
 * identical to what would be the page's slug.
 *
 * Judgment call (SPEC is silent on the exact rule): every directory segment
 * mirrors the tree verbatim, but the file's own leaf segment is the page's
 * front-matter `slug`, not its filename stem — consistent with §5.4's
 * "a slug is used verbatim and never re-slugified", which posts already
 * follow via RouteBuilder::postRoute(). An `index.md` contributes no leaf of
 * its own: its route *is* the directory it sits in.
 */
final class PagePathResolver
{
    /**
     * @param string $relativePath Content-root-relative path, e.g.
     *                              "pages/de/vortraege/index.md".
     * @param string $slug         The page's own front-matter slug.
     * @return string Route-relative path, no leading/trailing slash, no
     *                 "index" segment — e.g. "vortraege" or
     *                 "vortraege/coffee-factor", "" for the language root.
     */
    public function resolve(string $relativePath, string $slug): string
    {
        $segments = explode('/', $relativePath);
        // Drop "pages" and the language segment; what's left is directory
        // segments followed by the filename.
        $segments = array_slice($segments, 2);

        $filename          = array_pop($segments) ?? '';
        $directorySegments = $segments;

        if (strtolower($filename) === 'index.md') {
            return implode('/', $directorySegments);
        }

        return implode('/', [...$directorySegments, $slug]);
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One non-template file stage 7 (Emit, SPEC §11) produces: a feed, the
 * sitemap, the search index, robots.txt, security.txt, or the fingerprinted
 * stylesheet. Distinct from GeneratedFile (a route's rendered HTML, always
 * landing at `<route>/index.html`) because these don't come from a route at
 * all — $relativePath is given directly, e.g. "sitemap.xml" or
 * ".well-known/security.txt".
 */
final class ArtifactFile
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $contents,
    ) {
    }
}

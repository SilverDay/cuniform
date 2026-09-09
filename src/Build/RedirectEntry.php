<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One `old-path -> new-path` redirect (SPEC §8.3), from either source
 * `aliases` allows (SPEC §5.2, §1.2) — a document's own front matter listing
 * a path that used to point at it — or a manual line in
 * `content/redirects.map`. `$source` is provenance only, for error messages
 * when two entries collide on the same `$oldPath`.
 */
final class RedirectEntry
{
    public function __construct(
        public readonly string $oldPath,
        public readonly string $newPath,
        public readonly string $source,
    ) {
    }
}

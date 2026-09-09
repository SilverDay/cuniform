<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\FrontMatter\DocumentKind;

/**
 * One content file found by stage 2 (Discover, SPEC §10.1): its path, kind,
 * and language, plus the manifest facts (mtime, SHA-256) T24's incremental
 * cache key will need — computed now even though nothing consumes them yet,
 * since Discover is the one stage that ever reads the raw bytes for this
 * purpose and re-reading later would mean scanning the tree twice.
 */
final class DiscoveredDocument
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly string $relativePath,
        public readonly DocumentKind $kind,
        public readonly string $language,
        public readonly int $mtime,
        public readonly string $sha256,
    ) {
    }
}

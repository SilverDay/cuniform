<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * BuildCache's persisted state (SPEC §10.2): the nav-tree hash from the
 * build that produced it, and every document's CachedDocument, keyed by
 * `ParsedDocument::identifier()`.
 */
final class CacheManifest
{
    /**
     * @param array<string, CachedDocument> $documents
     */
    public function __construct(
        public readonly string $navHash,
        public readonly array $documents,
    ) {
    }

    public static function empty(): self
    {
        return new self('', []);
    }
}

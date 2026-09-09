<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One language-scoped tag (SPEC §7.10, §5.3) and every published post
 * carrying it, reverse-chronological. `slug` is what `/{L}tag/<slug>/`
 * uses; `label` is the tag exactly as an author wrote it in front matter.
 * Two differently-cased or accented tags that slugify to the same string
 * (e.g. "Awareness" and "awareness") share one TagArchive rather than
 * colliding on the same route — see ListingResolver.
 */
final class TagArchive
{
    /**
     * @param list<PostSummary> $posts
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly array $posts,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One series (SPEC §5.3: "groups posts within one language") and its
 * posts. Ordered reverse-chronological, same as every other listing type
 * (SPEC §6.1) — SPEC doesn't carve out a different order for series, so
 * this doesn't invent one. `slug` (via Slugifier, same as tags) is what
 * `/{L}series/<slug>/` uses; `label` is the series name as authored.
 */
final class SeriesArchive
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

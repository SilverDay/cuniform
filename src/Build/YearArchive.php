<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * One year archive (SPEC §8.1's `/{L}archive/<yyyy>/`) and its posts,
 * reverse-chronological.
 */
final class YearArchive
{
    /**
     * @param list<PostSummary> $posts
     */
    public function __construct(
        public readonly int $year,
        public readonly array $posts,
    ) {
    }
}

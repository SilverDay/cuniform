<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * Front matter keys common to posts and pages (SPEC §5.2).
 */
final class SharedFrontMatter
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly DocumentStatus $status,
        public readonly string $summary,
        public readonly ?string $translationKey,
        public readonly ?\DateTimeImmutable $updated,
        public readonly ?string $image,
        public readonly ?string $imageAlt,
        public readonly ?string $canonical,
        public readonly bool $noindex,
        public readonly array $aliases,
        public readonly bool $toc,
        public readonly ?string $sourceId,
    ) {
    }
}

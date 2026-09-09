<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;

/**
 * One discovered document plus its parsed, validated front matter (stage 3
 * — Parse, SPEC §10.1).
 */
final class ParsedDocument
{
    public function __construct(
        public readonly DiscoveredDocument $discovered,
        public readonly PostFrontMatter|PageFrontMatter $frontMatter,
    ) {
    }

    /**
     * The identifier translation grouping and route registration report
     * errors against. Based on the file's own path, not its slug — two
     * *different* documents (say a post and a page) are allowed to collide
     * on the same slug, and RouteTable's collision check only fires when two
     * *different* identifiers claim the same path, so an identifier must
     * stay unique per document regardless of what slug it declares.
     */
    public function identifier(): string
    {
        return "{$this->discovered->language}:{$this->discovered->relativePath}";
    }
}

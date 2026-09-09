<?php

declare(strict_types=1);

namespace Cuniform\Render;

use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;

/**
 * The result of running one document through the render adapter (C4): its
 * parsed front matter, the rendered body HTML, and the headings collected
 * while rendering it (for the future [toc] shortcode, T9).
 */
final class RenderedDocument
{
    /**
     * @param list<array{level: int, id: string, text: string}> $headings
     */
    public function __construct(
        public readonly PostFrontMatter|PageFrontMatter $frontMatter,
        public readonly string $bodyHtml,
        public readonly array $headings,
    ) {
    }
}

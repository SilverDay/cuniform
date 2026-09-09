<?php

declare(strict_types=1);

namespace Cuniform\Content\FrontMatter;

/**
 * A page's front matter (SPEC §5.2 shared keys + §6.2 page-only keys) plus its
 * Markdown body, with the front matter block already stripped.
 */
final class PageFrontMatter
{
    public function __construct(
        public readonly SharedFrontMatter $shared,
        public readonly string $template,
        public readonly ?string $navLabel,
        public readonly ?int $navOrder,
        public readonly ?string $navParent,
        public readonly ?NavGroup $navGroup,
        public readonly ?float $sitemapPriority,
        public readonly ?LegalRole $legal,
        public readonly string $body,
    ) {
    }
}

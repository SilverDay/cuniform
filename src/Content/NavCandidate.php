<?php

declare(strict_types=1);

namespace Cuniform\Content;

use Cuniform\Content\FrontMatter\NavGroup;

/**
 * One page's nav-relevant facts (SPEC §6.2, §6.3), as NavTreeBuilder needs
 * them. $path is route-relative under pages/<language>/ with no leading or
 * trailing slash and no "index" segment — e.g. "vortraege" for
 * pages/de/vortraege/index.md, "vortraege/coffee-factor" for a child page —
 * matching how RouteBuilder::pageRoute() already treats page paths.
 */
final class NavCandidate
{
    public function __construct(
        public readonly string $language,
        public readonly string $path,
        public readonly string $url,
        public readonly string $label,
        public readonly ?int $navOrder,
        public readonly ?string $navParent,
        public readonly ?NavGroup $navGroup,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Template;

/**
 * What layout.php receives (SPEC §9): the page's own ViewModel, its
 * already-rendered inner content, and the site-wide chrome data layout.php
 * owns (nav trees, site title). Nav trees are empty lists until T18 builds
 * the aggregation that populates them — rendering an empty nav is already
 * correct behaviour, not a stub.
 */
final class LayoutContext
{
    /**
     * @param list<NavItem> $primaryNav
     * @param list<NavItem> $footerNav
     */
    public function __construct(
        public readonly ViewModel $page,
        public readonly string $content,
        public readonly string $siteTitle,
        public readonly array $primaryNav = [],
        public readonly array $footerNav = [],
    ) {
    }
}

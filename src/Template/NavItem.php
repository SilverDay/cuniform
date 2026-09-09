<?php

declare(strict_types=1);

namespace Cuniform\Template;

/**
 * One entry in a nav tree. The tree itself is built by T18 (page hierarchy,
 * nav_order/nav_parent/nav_group); this is just the shape a template
 * iterates over, which doesn't need T18 to exist to be renderable.
 */
final class NavItem
{
    /**
     * @param list<NavItem> $children
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly array $children = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Admin\Reporting;

use Cuniform\Build\ListingSet;
use Cuniform\Build\RedirectEntry;
use Cuniform\Template\NavItem;

/**
 * AdminSiteReport::build()'s output: everything the page-list (nav tree),
 * taxonomy, and redirects admin screens (SPEC §13.3) need to display.
 */
final class AdminSiteReportResult
{
    /**
     * @param array<string, array{primary: list<NavItem>, footer: list<NavItem>}> $navByLanguage
     * @param list<RedirectEntry>                                                 $redirects
     * @param list<string>                                                        $warnings
     */
    public function __construct(
        public readonly array $navByLanguage,
        public readonly ListingSet $listing,
        public readonly array $redirects,
        public readonly array $warnings,
    ) {
    }
}

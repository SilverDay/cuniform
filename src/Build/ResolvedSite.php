<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Template\NavItem;

/**
 * Stage 4's (Resolve) output: every document that will actually be rendered
 * this build, plus the per-language nav trees built from the same set
 * (SPEC §6.2, §6.3).
 *
 * Tag/series indexes and year archives — also listed under this stage by
 * SPEC §10.1 — are deliberately NOT built here: ListingResolver reads this
 * class's own `documents` a second time to build them (T17), once
 * ListingTemplateStage existed as a consumer. Keeping that out of
 * SiteResolver/ResolvedSite avoids coupling routing/hreflang/nav
 * resolution (needed by every document, every build) to listing
 * aggregation (needed only by the generated-listing routes); see
 * ListingSet's own docblock. Redirect *compilation* is T21's own task,
 * not this one's; the `aliases` validation this stage *does* need
 * (SPEC §5.5: an alias must not collide with a real route) doesn't
 * require building the map itself.
 */
final class ResolvedSite
{
    /**
     * @param list<ResolvedDocument>                                   $documents
     * @param array<string, array{primary: list<NavItem>, footer: list<NavItem>}> $navByLanguage
     * @param list<string>                                              $warnings
     */
    public function __construct(
        public readonly array $documents,
        public readonly array $navByLanguage,
        public readonly array $warnings,
    ) {
    }
}

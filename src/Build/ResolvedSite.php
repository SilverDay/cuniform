<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\Template\NavItem;

/**
 * Stage 4's (Resolve) output: every document that will actually be rendered
 * this build, plus the per-language nav trees built from the same set
 * (SPEC §6.2, §6.3).
 *
 * What SPEC §10.1 also lists for this stage — tag/series indexes, prev/next,
 * and compiling `aliases` into `redirects.map` — is deliberately not built
 * here yet: nothing consumes it. No tag.php/series.php template exists (T17
 * remains partial), and redirect *compilation* is T21's own task, not this
 * one's; the `aliases` validation this stage *does* need (SPEC §5.5: an
 * alias must not collide with a real route) doesn't require building the
 * map itself. Building that data now, ahead of a consumer, would be the
 * exact kind of speculative structure this project's conventions ask not to
 * add — same reasoning IncludedPageRepository was left as an interface
 * until T19 had a concrete corpus to resolve against.
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

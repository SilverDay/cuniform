<?php

declare(strict_types=1);

namespace Cuniform\I18n;

/**
 * One page's full hreflang picture (SPEC §7.5): its alternates (always
 * including a self-reference, plus x-default for a multi-language site) and
 * its own canonical URL. The same $alternates list doubles as the source
 * for og:locale:alternate meta tags — same data, a template-level rendering
 * choice, not a second component.
 */
final class HreflangSet
{
    /**
     * @param list<HreflangEntry> $alternates
     */
    public function __construct(
        public readonly array $alternates,
        public readonly string $canonical,
    ) {
    }
}

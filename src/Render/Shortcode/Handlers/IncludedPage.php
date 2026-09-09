<?php

declare(strict_types=1);

namespace Cuniform\Render\Shortcode\Handlers;

/**
 * A page resolved for `[include]` (SPEC §6.5): its language, so IncludeHandler
 * can enforce the same-language rule, and its already-rendered body HTML.
 */
final class IncludedPage
{
    public function __construct(
        public readonly string $language,
        public readonly string $bodyHtml,
    ) {
    }
}

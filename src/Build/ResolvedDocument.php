<?php

declare(strict_types=1);

namespace Cuniform\Build;

use Cuniform\I18n\HreflangSet;

/**
 * One document that made it into this build (SPEC §5.6 — a draft, or a
 * scheduled document not yet due, never reaches this stage) plus what stage
 * 4 (Resolve) computed for it: its final route and hreflang set.
 */
final class ResolvedDocument
{
    public function __construct(
        public readonly ParsedDocument $parsed,
        public readonly string $url,
        public readonly ?HreflangSet $hreflang,
    ) {
    }
}

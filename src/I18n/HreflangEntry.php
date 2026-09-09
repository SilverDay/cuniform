<?php

declare(strict_types=1);

namespace Cuniform\I18n;

/**
 * One `<link rel="alternate">` entry (SPEC §7.5): $hreflang is either a
 * language code or the literal string 'x-default'.
 */
final class HreflangEntry
{
    public function __construct(
        public readonly string $hreflang,
        public readonly string $url,
    ) {
    }
}

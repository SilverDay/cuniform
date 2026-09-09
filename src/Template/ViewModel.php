<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\UiStringCatalogue;

/**
 * What every template receives, one instance, immutable (SPEC §9). This is
 * the shared cross-cutting shape layout.php needs regardless of page type
 * (language, hreflang, canonical, t()) — a page-type-specific ViewModel
 * (post, index, tag, ...) extends this with its own data once that
 * template actually exists (T17); inventing those shapes now, before any
 * template needs them, would just be guessing.
 */
abstract class ViewModel
{
    public function __construct(
        public readonly string $language,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $canonicalUrl,
        public readonly ?HreflangSet $hreflang,
        private readonly UiStringCatalogue $strings,
    ) {
    }

    public function t(string $key): string
    {
        return $this->strings->get($this->language, $key);
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\I18n\UiStringCatalogue;

/**
 * The per-language 404 (SPEC §7.12, §8.1's `/{L}404.html`) — rendered
 * through the same layout.php chain as every other page, with that
 * language's own strings and navigation. `noindex` is always true: a
 * 404 has nothing search engines should keep. See IndexViewModel for
 * why hreflang is null (no translation group for an error document).
 */
final class ErrorViewModel extends ViewModel
{
    public function __construct(
        string $language,
        string $title,
        string $summary,
        string $canonicalUrl,
        UiStringCatalogue $strings,
        public readonly string $homeUrl,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, true, false, []);
    }
}

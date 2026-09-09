<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\I18n\UiStringCatalogue;

/**
 * The client-side search page (SPEC §8.1's `/{L}search/`, §11.3, §14.1).
 * Carries no post data of its own — the page ships empty and fetches
 * `search-index.json` at runtime via an external script (never inline;
 * SPEC §14.1 prefers no inline script at all on public pages, and
 * `search.js` is exactly the kind of thing that should ship as an
 * external file instead). See IndexViewModel for why hreflang is null.
 */
final class SearchViewModel extends ViewModel
{
    public function __construct(
        string $language,
        string $title,
        string $summary,
        string $canonicalUrl,
        UiStringCatalogue $strings,
        public readonly string $searchScriptUrl,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, false, false, []);
    }
}

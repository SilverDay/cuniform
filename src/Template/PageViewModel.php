<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\UiStringCatalogue;

final class PageViewModel extends ViewModel
{
    /**
     * @param list<array{level: int, id: string, text: string}> $headings
     */
    public function __construct(
        string $language,
        string $title,
        string $summary,
        string $canonicalUrl,
        ?HreflangSet $hreflang,
        UiStringCatalogue $strings,
        public readonly string $bodyHtml,
        bool $noindex,
        bool $toc,
        array $headings,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, $hreflang, $strings, $noindex, $toc, $headings);
    }
}

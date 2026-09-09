<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\I18n\HreflangSet;
use Cuniform\I18n\UiStringCatalogue;

final class PostViewModel extends ViewModel
{
    /**
     * @param list<string>                                        $tags
     * @param list<array{level: int, id: string, text: string}>   $headings
     */
    public function __construct(
        string $language,
        string $title,
        string $summary,
        string $canonicalUrl,
        ?HreflangSet $hreflang,
        UiStringCatalogue $strings,
        public readonly string $bodyHtml,
        public readonly string $formattedDate,
        public readonly ?string $formattedUpdated,
        public readonly array $tags,
        public readonly ?string $series,
        public readonly bool $noindex,
        public readonly bool $toc,
        public readonly array $headings,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, $hreflang, $strings);
    }
}

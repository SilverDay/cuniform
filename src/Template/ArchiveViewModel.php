<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Build\PostSummary;
use Cuniform\I18n\UiStringCatalogue;

/**
 * A year archive (SPEC §8.1's `/{L}archive/<yyyy>/`, not paginated —
 * see RouteBuilder::archiveRoute()). See IndexViewModel for why hreflang
 * is always null here.
 */
final class ArchiveViewModel extends ViewModel
{
    /**
     * @param list<PostSummary> $posts
     */
    public function __construct(
        string $language,
        string $title,
        string $summary,
        string $canonicalUrl,
        UiStringCatalogue $strings,
        public readonly int $year,
        public readonly array $posts,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, false, false, []);
    }
}

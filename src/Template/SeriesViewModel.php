<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Build\PostSummary;
use Cuniform\I18n\UiStringCatalogue;

/**
 * A series index (SPEC §8.1's `/{L}series/<series>/`, not paginated —
 * see RouteBuilder::seriesRoute()). See IndexViewModel for why hreflang
 * is always null here.
 */
final class SeriesViewModel extends ViewModel
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
        public readonly string $seriesLabel,
        public readonly array $posts,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, false, false, []);
    }
}

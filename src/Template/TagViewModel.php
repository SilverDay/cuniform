<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Build\PostSummary;
use Cuniform\I18n\UiStringCatalogue;

/**
 * A tag archive (SPEC §8.1's `/{L}tag/<tag>/`, paginated). See
 * IndexViewModel for why hreflang is always null here.
 */
final class TagViewModel extends ViewModel
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
        public readonly string $tagLabel,
        public readonly array $posts,
        public readonly Pagination $pagination,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, false, false, []);
    }
}

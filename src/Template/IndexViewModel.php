<?php

declare(strict_types=1);

namespace Cuniform\Template;

use Cuniform\Build\PostSummary;
use Cuniform\I18n\UiStringCatalogue;

/**
 * The post index (SPEC §8.1's `/{L}` + `/{L}page/2/`), one page of
 * `posts_per_page` posts at a time. Generated pages carry no hreflang set
 * (null, same as ErrorViewModel/SearchViewModel/etc.) — a listing page
 * isn't a translated document with a `translation_key`, so there's no
 * natural per-language counterpart to advertise; the language switcher
 * partial already renders nothing when hreflang is null (SPEC §7.7).
 */
final class IndexViewModel extends ViewModel
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
        public readonly array $posts,
        public readonly Pagination $pagination,
    ) {
        parent::__construct($language, $title, $summary, $canonicalUrl, null, $strings, false, false, []);
    }
}

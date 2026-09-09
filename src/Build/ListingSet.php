<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * The aggregated, per-language listing data SPEC §10.1's Resolve stage
 * lists (tag/series indexes, and — generalizing the same idea — year
 * archives and the plain post list the home index paginates over), built
 * by ListingResolver once ListingTemplateStage (T17) exists to consume it.
 * ResolvedSite deliberately doesn't carry this itself — it's a second,
 * independent read of the same ResolvedSite, not a property of resolving
 * routes/hreflang/nav, and every consumer (ListingTemplateStage,
 * SitemapGenerator) needs the whole set, not one document at a time.
 */
final class ListingSet
{
    /**
     * @param array<string, list<PostSummary>>   $postsByLanguage  all included posts, reverse-chronological
     * @param array<string, list<TagArchive>>    $tagsByLanguage
     * @param array<string, list<SeriesArchive>> $seriesByLanguage
     * @param array<string, list<YearArchive>>   $yearsByLanguage
     */
    public function __construct(
        public readonly array $postsByLanguage,
        public readonly array $tagsByLanguage,
        public readonly array $seriesByLanguage,
        public readonly array $yearsByLanguage,
    ) {
    }
}

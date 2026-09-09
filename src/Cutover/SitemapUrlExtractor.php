<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * Reads `<loc>` entries out of a sitemap document (SPEC §19 item 5: "a
 * crawl of the existing sitemap is the practical source"). Handles both
 * shapes a real site's sitemap.xml might be: a plain `<urlset>` of pages,
 * or a `<sitemapindex>` of further sitemaps (common on WordPress —
 * SPEC's own Appendix A assumption, §19 item 3 — e.g. Yoast's
 * `sitemap_index.xml` referencing `post-sitemap.xml`, `page-sitemap.xml`,
 * ...). Which one it got, and recursing into an index's sub-sitemaps, is
 * LegacyUrlCrawler's job — this class only parses one document at a time.
 */
final class SitemapUrlExtractor
{
    /**
     * @return array{type: 'urlset'|'sitemapindex'|null, urls: list<string>}
     *         `type: null` when `$xml` doesn't parse as XML, or isn't one
     *         of the two recognized root elements.
     */
    public function extract(string $xml): array
    {
        $previousSetting = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml);
        } finally {
            libxml_use_internal_errors($previousSetting);
        }

        if ($document === false) {
            return ['type' => null, 'urls' => []];
        }

        $rootName = $document->getName();
        if ($rootName !== 'urlset' && $rootName !== 'sitemapindex') {
            return ['type' => null, 'urls' => []];
        }

        $childTag = $rootName === 'urlset' ? 'url' : 'sitemap';

        $urls = [];
        foreach ($document->{$childTag} as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }

        return ['type' => $rootName, 'urls' => $urls];
    }
}

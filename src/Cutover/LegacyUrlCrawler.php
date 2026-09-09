<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * Legacy URL enumeration (SPEC §15.5, §19 item 5): "A crawl of the
 * existing sitemap is the practical source; if the current site has no
 * sitemap, a `wget --spider --recursive` run produces one." `crawl()`
 * tries `{baseUrl}/sitemap.xml` first (recursing through a sitemap index
 * if that's what it finds); only when no usable sitemap exists does it
 * fall back to a same-host, robots.txt-respecting spider.
 *
 * This is enumeration only — SPEC §19 item 5 is explicit that this is
 * "the complete list of live URLs," not a content mirror. §15.5's
 * separate `wget --mirror` (T26, the frozen-snapshot option) downloads
 * full pages for archival; this class exists to answer "what paths
 * exist," nothing more, which is why a sitemap-sourced entry never
 * carries a status code (see LegacyUrlEntry's own docblock).
 */
final class LegacyUrlCrawler
{
    public function __construct(
        private readonly HttpFetcher $fetcher,
        private readonly SitemapUrlExtractor $sitemapExtractor = new SitemapUrlExtractor(),
        private readonly LinkExtractor $linkExtractor = new LinkExtractor(),
        private readonly int $maxPages = 5000,
        private readonly int $maxSitemaps = 100,
    ) {
    }

    /**
     * @throws CutoverException When $baseUrl isn't a valid absolute http(s) URL.
     */
    public function crawl(string $baseUrl): UrlInventory
    {
        $baseUrl = rtrim($baseUrl, '/');
        $host    = parse_url($baseUrl, \PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw CutoverException::invalidBaseUrl($baseUrl);
        }

        $sitemapUrls = $this->trySitemap($baseUrl);
        if ($sitemapUrls !== null) {
            $entries = array_map(
                fn (string $url): LegacyUrlEntry => new LegacyUrlEntry($this->pathOf($url), null, null, UrlSource::Sitemap),
                $sitemapUrls
            );

            return new UrlInventory($baseUrl, $entries);
        }

        return $this->crawlSite($baseUrl);
    }

    /**
     * @return list<string>|null null when no usable sitemap exists — the
     *                            caller falls back to spidering.
     */
    private function trySitemap(string $baseUrl): ?array
    {
        $response = $this->tryFetch($baseUrl . '/sitemap.xml');
        if ($response === null || $response->statusCode !== 200) {
            return null;
        }

        $result = $this->sitemapExtractor->extract($response->body);

        if ($result['type'] === 'urlset') {
            return $result['urls'] === [] ? null : $result['urls'];
        }

        if ($result['type'] !== 'sitemapindex') {
            return null;
        }

        $urls = [];
        foreach (array_slice($result['urls'], 0, $this->maxSitemaps) as $subSitemapUrl) {
            $subResponse = $this->tryFetch($subSitemapUrl);
            if ($subResponse === null || $subResponse->statusCode !== 200) {
                continue;
            }

            $subResult = $this->sitemapExtractor->extract($subResponse->body);
            if ($subResult['type'] === 'urlset') {
                $urls = [...$urls, ...$subResult['urls']];
            }
        }

        return $urls === [] ? null : $urls;
    }

    private function crawlSite(string $baseUrl): UrlInventory
    {
        $robots = $this->fetchRobots($baseUrl);

        /** @var array<string, true> $visited */
        $visited = [];
        $queue   = [$baseUrl . '/'];
        $entries = [];

        while ($queue !== [] && count($visited) < $this->maxPages) {
            $url = array_shift($queue);
            if (isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;
            $path          = $this->pathOf($url);

            if (!$robots->isAllowed($path)) {
                continue;
            }

            $response = $this->tryFetch($url);
            if ($response === null) {
                $entries[] = new LegacyUrlEntry($path, null, null, UrlSource::Crawl);

                continue;
            }

            $entries[] = new LegacyUrlEntry($path, $response->statusCode, $response->contentType, UrlSource::Crawl);

            if ($response->statusCode !== 200 || !$this->isHtml($response->contentType)) {
                continue;
            }

            foreach ($this->linkExtractor->extract($response->body, $url) as $link) {
                if (!isset($visited[$link])) {
                    $queue[] = $link;
                }
            }
        }

        return new UrlInventory($baseUrl, $entries);
    }

    private function fetchRobots(string $baseUrl): RobotsTxt
    {
        $response = $this->tryFetch($baseUrl . '/robots.txt');

        return $response !== null && $response->statusCode === 200
            ? RobotsTxt::parse($response->body)
            : RobotsTxt::allowAll();
    }

    private function tryFetch(string $url): ?HttpResponse
    {
        try {
            return $this->fetcher->fetch($url);
        } catch (CutoverException) {
            return null;
        }
    }

    private function isHtml(?string $contentType): bool
    {
        return $contentType !== null && str_contains($contentType, 'html');
    }

    private function pathOf(string $url): string
    {
        $path  = parse_url($url, \PHP_URL_PATH);
        $path  = is_string($path) && $path !== '' ? $path : '/';
        $query = parse_url($url, \PHP_URL_QUERY);

        return is_string($query) ? "{$path}?{$query}" : $path;
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\CutoverException;
use Cuniform\Cutover\LegacyUrlCrawler;
use Cuniform\Cutover\UrlSource;
use PHPUnit\Framework\TestCase;

final class LegacyUrlCrawlerTest extends TestCase
{
    public function testInvalidBaseUrlThrows(): void
    {
        $this->expectException(CutoverException::class);
        (new LegacyUrlCrawler(new FakeHttpFetcher()))->crawl('not-a-url');
    }

    public function testPrefersASitemapWhenOneExists(): void
    {
        $fetcher = (new FakeHttpFetcher())->respond(
            'https://legacy.test/sitemap.xml',
            200,
            $this->urlset(['https://legacy.test/a/', 'https://legacy.test/b/']),
            'application/xml'
        );

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        self::assertSame('https://legacy.test', $inventory->baseUrl);
        self::assertCount(2, $inventory->entries);
        self::assertSame('/a/', $inventory->entries[0]->path);
        self::assertSame(UrlSource::Sitemap, $inventory->entries[0]->source);
        self::assertNull($inventory->entries[0]->statusCode);
        // Sitemap found — no spidering should have happened at all.
        self::assertNotContains('https://legacy.test/', $fetcher->requestedUrls);
    }

    public function testRecursesThroughASitemapIndex(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 200, $this->sitemapIndex([
                'https://legacy.test/post-sitemap.xml',
                'https://legacy.test/page-sitemap.xml',
            ]), 'application/xml')
            ->respond('https://legacy.test/post-sitemap.xml', 200, $this->urlset(['https://legacy.test/post-a/']), 'application/xml')
            ->respond('https://legacy.test/page-sitemap.xml', 200, $this->urlset(['https://legacy.test/about/']), 'application/xml');

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        $paths = array_map(static fn ($e) => $e->path, $inventory->entries);
        self::assertSame(['/post-a/', '/about/'], $paths);
        foreach ($inventory->entries as $entry) {
            self::assertSame(UrlSource::Sitemap, $entry->source);
        }
    }

    public function testFallsBackToSpideringWhenNoSitemapExists(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 404)
            ->respond('https://legacy.test/', 200, '<a href="/post-a/">a</a><a href="/post-b/">b</a>', 'text/html')
            ->respond('https://legacy.test/post-a/', 200, '<p>a</p>', 'text/html')
            ->respond('https://legacy.test/post-b/', 200, '<p>b</p>', 'text/html');

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        $paths = array_map(static fn ($e) => $e->path, $inventory->entries);
        sort($paths);
        self::assertSame(['/', '/post-a/', '/post-b/'], $paths);
        foreach ($inventory->entries as $entry) {
            self::assertSame(UrlSource::Crawl, $entry->source);
        }
        self::assertSame(200, $inventory->entries[0]->statusCode);
    }

    public function testSpideringRecordsANonHtmlPageButDoesNotFollowLinksFromIt(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 404)
            ->respond('https://legacy.test/', 200, '<a href="/file.pdf">pdf</a>', 'text/html')
            ->respond('https://legacy.test/file.pdf', 200, '%PDF-1.4 binary garbage', 'application/pdf');

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        $paths = array_map(static fn ($e) => $e->path, $inventory->entries);
        sort($paths);
        self::assertSame(['/', '/file.pdf'], $paths, 'the PDF is recorded, but never parsed for further links');
    }

    public function testSpideringRespectsRobotsTxtDisallow(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 200, "User-agent: *\nDisallow: /wp-admin/\n")
            ->respond('https://legacy.test/', 200, '<a href="/wp-admin/">admin</a><a href="/post/">post</a>', 'text/html')
            ->respond('https://legacy.test/post/', 200, '<p>post</p>', 'text/html');

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        $paths = array_map(static fn ($e) => $e->path, $inventory->entries);
        self::assertNotContains('/wp-admin/', $paths);
        self::assertContains('/post/', $paths);
    }

    public function testSpideringDeduplicatesAcrossMultipleLinkingPages(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 404)
            ->respond('https://legacy.test/', 200, '<a href="/a/">a</a><a href="/b/">b</a>', 'text/html')
            ->respond('https://legacy.test/a/', 200, '<a href="/b/">b again</a>', 'text/html')
            ->respond('https://legacy.test/b/', 200, '<p>b</p>', 'text/html');

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        $paths = array_map(static fn ($e) => $e->path, $inventory->entries);
        self::assertCount(3, $paths, '/b/ must only be visited once');
    }

    public function testMaxPagesCapsTheSpider(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 404)
            ->respond('https://legacy.test/', 200, '<a href="/a/">a</a><a href="/b/">b</a><a href="/c/">c</a>', 'text/html')
            ->respond('https://legacy.test/a/', 200, '', 'text/html')
            ->respond('https://legacy.test/b/', 200, '', 'text/html')
            ->respond('https://legacy.test/c/', 200, '', 'text/html');

        $inventory = (new LegacyUrlCrawler($fetcher, maxPages: 2))->crawl('https://legacy.test');

        self::assertCount(2, $inventory->entries);
    }

    public function testAFetchFailureDuringSpideringIsRecordedWithNullStatus(): void
    {
        $fetcher = (new FakeHttpFetcher())
            ->respond('https://legacy.test/sitemap.xml', 404)
            ->respond('https://legacy.test/robots.txt', 404);
        // https://legacy.test/ itself is not stubbed — fetch() throws.

        $inventory = (new LegacyUrlCrawler($fetcher))->crawl('https://legacy.test');

        self::assertCount(1, $inventory->entries);
        self::assertNull($inventory->entries[0]->statusCode);
    }

    /**
     * @param list<string> $urls
     */
    private function urlset(array $urls): string
    {
        $items = implode('', array_map(static fn (string $u): string => "<url><loc>{$u}</loc></url>", $urls));

        return '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $items . '</urlset>';
    }

    /**
     * @param list<string> $urls
     */
    private function sitemapIndex(array $urls): string
    {
        $items = implode('', array_map(static fn (string $u): string => "<sitemap><loc>{$u}</loc></sitemap>", $urls));

        return '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $items . '</sitemapindex>';
    }
}

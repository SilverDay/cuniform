<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\SitemapUrlExtractor;
use PHPUnit\Framework\TestCase;

final class SitemapUrlExtractorTest extends TestCase
{
    public function testExtractsUrlsFromAPlainUrlset(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://legacy.test/post-a/</loc></url>
                <url><loc>https://legacy.test/post-b/</loc></url>
            </urlset>
            XML;

        $result = (new SitemapUrlExtractor())->extract($xml);

        self::assertSame('urlset', $result['type']);
        self::assertSame(['https://legacy.test/post-a/', 'https://legacy.test/post-b/'], $result['urls']);
    }

    public function testExtractsSubSitemapUrlsFromASitemapIndex(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <sitemap><loc>https://legacy.test/post-sitemap.xml</loc></sitemap>
                <sitemap><loc>https://legacy.test/page-sitemap.xml</loc></sitemap>
            </sitemapindex>
            XML;

        $result = (new SitemapUrlExtractor())->extract($xml);

        self::assertSame('sitemapindex', $result['type']);
        self::assertSame(['https://legacy.test/post-sitemap.xml', 'https://legacy.test/page-sitemap.xml'], $result['urls']);
    }

    public function testMalformedXmlReturnsNullType(): void
    {
        $result = (new SitemapUrlExtractor())->extract('not xml at all');

        self::assertNull($result['type']);
        self::assertSame([], $result['urls']);
    }

    public function testUnrecognizedRootElementReturnsNullType(): void
    {
        $result = (new SitemapUrlExtractor())->extract('<rss version="2.0"><channel></channel></rss>');

        self::assertNull($result['type']);
        self::assertSame([], $result['urls']);
    }

    public function testEmptyLocIsSkipped(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc></loc></url>
                <url><loc>https://legacy.test/real/</loc></url>
            </urlset>
            XML;

        $result = (new SitemapUrlExtractor())->extract($xml);

        self::assertSame(['https://legacy.test/real/'], $result['urls']);
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\LinkExtractor;
use PHPUnit\Framework\TestCase;

final class LinkExtractorTest extends TestCase
{
    public function testExtractsAbsoluteSameHostLinks(): void
    {
        $html = '<a href="https://legacy.test/post-a/">a</a><a href="https://legacy.test/post-b/">b</a>';

        $urls = (new LinkExtractor())->extract($html, 'https://legacy.test/');

        self::assertSame(['https://legacy.test/post-a/', 'https://legacy.test/post-b/'], $urls);
    }

    public function testResolvesRootRelativeLinks(): void
    {
        $html = '<a href="/about/">about</a>';

        $urls = (new LinkExtractor())->extract($html, 'https://legacy.test/blog/post/');

        self::assertSame(['https://legacy.test/about/'], $urls);
    }

    public function testResolvesDocumentRelativeLinks(): void
    {
        $html = '<a href="sibling/">sibling</a>';

        $urls = (new LinkExtractor())->extract($html, 'https://legacy.test/blog/post/');

        self::assertSame(['https://legacy.test/blog/post/sibling/'], $urls);
    }

    public function testResolvesDotDotSegments(): void
    {
        $html = '<a href="../other/">other</a>';

        $urls = (new LinkExtractor())->extract($html, 'https://legacy.test/blog/post/');

        self::assertSame(['https://legacy.test/blog/other/'], $urls);
    }

    public function testDropsOffHostLinks(): void
    {
        $html = '<a href="https://elsewhere.test/x/">external</a>';

        self::assertSame([], (new LinkExtractor())->extract($html, 'https://legacy.test/'));
    }

    public function testDropsFragmentMailtoTelAndJavascriptLinks(): void
    {
        $html = '<a href="#top">frag</a><a href="mailto:a@example.com">mail</a>'
            . '<a href="tel:+123">tel</a><a href="javascript:void(0)">js</a>';

        self::assertSame([], (new LinkExtractor())->extract($html, 'https://legacy.test/'));
    }

    public function testStripsFragmentFromAnOtherwiseValidLink(): void
    {
        $html = '<a href="/post/#section">post</a>';

        self::assertSame(['https://legacy.test/post/'], (new LinkExtractor())->extract($html, 'https://legacy.test/'));
    }

    public function testDeduplicatesRepeatedLinks(): void
    {
        $html = '<a href="/post/">a</a><a href="/post/">b again</a>';

        self::assertSame(['https://legacy.test/post/'], (new LinkExtractor())->extract($html, 'https://legacy.test/'));
    }

    public function testProtocolRelativeLinkInheritsTheBaseScheme(): void
    {
        $html = '<a href="//legacy.test/post/">p</a>';

        self::assertSame(['https://legacy.test/post/'], (new LinkExtractor())->extract($html, 'https://legacy.test/'));
    }
}

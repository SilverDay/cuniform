<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\AbsoluteUrlRewriter;
use PHPUnit\Framework\TestCase;

final class AbsoluteUrlRewriterTest extends TestCase
{
    public function testRewritesRootRelativeSrcAndHref(): void
    {
        $html = '<img src="/media/2026/03/photo.jpg"><a href="/de/other/">x</a>';

        $result = (new AbsoluteUrlRewriter())->rewrite($html, 'https://blog.silverday.de');

        self::assertSame(
            '<img src="https://blog.silverday.de/media/2026/03/photo.jpg">'
            . '<a href="https://blog.silverday.de/de/other/">x</a>',
            $result
        );
    }

    public function testLeavesAlreadyAbsoluteUrlsAlone(): void
    {
        $html = '<a href="https://example.com/x">x</a>';

        self::assertSame($html, (new AbsoluteUrlRewriter())->rewrite($html, 'https://blog.silverday.de'));
    }

    public function testLeavesFragmentAndMailtoLinksAlone(): void
    {
        $html = '<a href="#section">x</a><a href="mailto:a@example.com">y</a>';

        self::assertSame($html, (new AbsoluteUrlRewriter())->rewrite($html, 'https://blog.silverday.de'));
    }

    public function testStripsATrailingSlashFromTheBaseUrl(): void
    {
        $html = '<img src="/x.jpg">';

        self::assertSame(
            '<img src="https://blog.silverday.de/x.jpg">',
            (new AbsoluteUrlRewriter())->rewrite($html, 'https://blog.silverday.de/')
        );
    }
}

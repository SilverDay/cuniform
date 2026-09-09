<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\Handlers\FigureHandler;
use PHPUnit\Framework\TestCase;

final class FigureHandlerTest extends TestCase
{
    public function testRendersImageWithAltAndCaption(): void
    {
        $html = (new FigureHandler())->render([
            'src'     => '/media/2026/03/photo.jpg',
            'alt'     => 'A cat',
            'caption' => 'Our cat',
        ], null);

        self::assertStringContainsString('<figure>', $html);
        self::assertStringContainsString('src="/media/2026/03/photo.jpg"', $html);
        self::assertStringContainsString('alt="A cat"', $html);
        self::assertStringContainsString('<figcaption>Our cat</figcaption>', $html);
        self::assertStringContainsString('loading="lazy"', $html);
        self::assertStringContainsString('decoding="async"', $html);
    }

    public function testOmitsFigcaptionWhenNoCaptionGiven(): void
    {
        $html = (new FigureHandler())->render(['src' => '/x.jpg', 'alt' => ''], null);

        self::assertStringNotContainsString('<figcaption>', $html);
    }

    public function testRejectsJavascriptSchemeInSrc(): void
    {
        $html = (new FigureHandler())->render(['src' => 'javascript:alert(1)', 'alt' => 'x'], null);

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('src="#"', $html);
    }

    public function testRejectsDataSchemeInSrc(): void
    {
        $html = (new FigureHandler())->render(['src' => 'data:text/html,<script>1</script>', 'alt' => 'x'], null);

        self::assertStringNotContainsString('data:text', $html);
    }

    public function testEscapesAltAndCaption(): void
    {
        $html = (new FigureHandler())->render([
            'src'     => '/x.jpg',
            'alt'     => '<script>alert(1)</script>',
            'caption' => '<b>bold</b>',
        ], null);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }

    public function testIncludesNumericWidthAndHeight(): void
    {
        $html = (new FigureHandler())->render(['src' => '/x.jpg', 'alt' => '', 'width' => '800', 'height' => '600'], null);

        self::assertStringContainsString('width="800"', $html);
        self::assertStringContainsString('height="600"', $html);
    }

    public function testIgnoresNonNumericWidth(): void
    {
        $html = (new FigureHandler())->render(['src' => '/x.jpg', 'alt' => '', 'width' => 'huge'], null);

        self::assertStringNotContainsString('width=', $html);
    }

    public function testIsBlockLevelWithNoBody(): void
    {
        $handler = new FigureHandler();

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('figure', $handler->name());
    }
}

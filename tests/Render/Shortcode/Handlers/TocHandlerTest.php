<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Md2Html;
use Cuniform\Render\Shortcode\Handlers\TocHandler;
use PHPUnit\Framework\TestCase;

final class TocHandlerTest extends TestCase
{
    public function testRendersALinkPerHeadingAfterConvertHasRun(): void
    {
        $renderer = new Md2Html(['headless' => true]);
        $renderer->convert("# First\n\n## Second\n");

        $html = (new TocHandler($renderer))->render([], null);

        self::assertStringContainsString('<nav class="toc">', $html);
        self::assertStringContainsString('<a href="#first">First</a>', $html);
        self::assertStringContainsString('<a href="#second">Second</a>', $html);
        self::assertStringContainsString('toc-level-1', $html);
        self::assertStringContainsString('toc-level-2', $html);
    }

    public function testRendersNothingWhenTheDocumentHasNoHeadings(): void
    {
        $renderer = new Md2Html(['headless' => true]);
        $renderer->convert('Just a paragraph.');

        $html = (new TocHandler($renderer))->render([], null);

        self::assertSame('', $html);
    }

    public function testIsBlockLevelWithNoBody(): void
    {
        $handler = new TocHandler(new Md2Html(['headless' => true]));

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('toc', $handler->name());
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\Handlers\DetailsHandler;
use Cuniform\Render\Shortcode\ShortcodeBodyType;
use PHPUnit\Framework\TestCase;

final class DetailsHandlerTest extends TestCase
{
    public function testRendersNativeDetailsElement(): void
    {
        $html = (new DetailsHandler())->render(['summary' => 'Click to expand'], '<p>Hidden content.</p>');

        self::assertSame('<details><summary>Click to expand</summary><p>Hidden content.</p></details>', $html);
    }

    public function testEscapesSummary(): void
    {
        $html = (new DetailsHandler())->render(['summary' => '<script>alert(1)</script>'], 'body');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBodyTypeIsMarkdown(): void
    {
        $handler = new DetailsHandler();

        self::assertSame(ShortcodeBodyType::Markdown, $handler->bodyType());
        self::assertTrue($handler->isBlockLevel());
        self::assertSame('details', $handler->name());
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\Handlers\VideoHandler;
use PHPUnit\Framework\TestCase;

final class VideoHandlerTest extends TestCase
{
    public function testRendersSelfHostedVideoWithControls(): void
    {
        $html = (new VideoHandler())->render(['src' => '/media/2026/clip.mp4'], null);

        self::assertStringContainsString('<video', $html);
        self::assertStringContainsString('controls', $html);
        self::assertStringContainsString('src="/media/2026/clip.mp4"', $html);
    }

    public function testOmitsPosterAttributeWhenNotGiven(): void
    {
        $html = (new VideoHandler())->render(['src' => '/x.mp4'], null);

        self::assertStringNotContainsString('poster=', $html);
    }

    public function testIncludesSanitizedPosterWhenGiven(): void
    {
        $html = (new VideoHandler())->render(['src' => '/x.mp4', 'poster' => '/media/poster.jpg'], null);

        self::assertStringContainsString('poster="/media/poster.jpg"', $html);
    }

    public function testRejectsJavascriptSchemeInSrc(): void
    {
        $html = (new VideoHandler())->render(['src' => 'javascript:alert(1)'], null);

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('src="#"', $html);
    }

    public function testRejectsJavascriptSchemeInPoster(): void
    {
        $html = (new VideoHandler())->render(['src' => '/x.mp4', 'poster' => 'javascript:alert(1)'], null);

        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testIsBlockLevel(): void
    {
        $handler = new VideoHandler();

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('video', $handler->name());
    }
}

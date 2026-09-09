<?php

declare(strict_types=1);

namespace Cuniform\Tests\Render\Shortcode\Handlers;

use Cuniform\Render\Shortcode\Handlers\EmbedHandler;
use PHPUnit\Framework\TestCase;

final class EmbedHandlerTest extends TestCase
{
    public function testRendersAYoutubeFacadeLink(): void
    {
        $html = (new EmbedHandler())->render(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], null);

        self::assertStringContainsString('href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"', $html);
        self::assertStringContainsString('data-embed-provider="youtube"', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function testRendersAVimeoFacadeLink(): void
    {
        $html = (new EmbedHandler())->render(['provider' => 'vimeo', 'id' => '12345678'], null);

        self::assertStringContainsString('href="https://vimeo.com/12345678"', $html);
    }

    public function testUnknownProviderRendersNothing(): void
    {
        $html = (new EmbedHandler())->render(['provider' => 'evil-tracker', 'id' => 'x'], null);

        self::assertSame('', $html);
    }

    public function testMalformedIdRendersNothing(): void
    {
        $html = (new EmbedHandler())->render(['provider' => 'youtube', 'id' => '"><script>alert(1)</script>'], null);

        self::assertSame('', $html);
    }

    public function testDoesNotFetchAnyThirdPartyThumbnailUrl(): void
    {
        $html = (new EmbedHandler())->render(['provider' => 'youtube', 'id' => 'abc123'], null);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<iframe', $html);
    }

    public function testIsBlockLevel(): void
    {
        $handler = new EmbedHandler();

        self::assertTrue($handler->isBlockLevel());
        self::assertSame('embed', $handler->name());
    }
}

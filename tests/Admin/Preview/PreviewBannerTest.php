<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Preview;

use Cuniform\Admin\Preview\PreviewBanner;
use PHPUnit\Framework\TestCase;

final class PreviewBannerTest extends TestCase
{
    public function testStripUndoesExactlyWhatInjectAdded(): void
    {
        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head></head>\n<body>\n<main>Hello</main>\n</body>\n</html>\n";

        $withBanner = PreviewBanner::inject($html);

        self::assertNotSame($html, $withBanner);
        self::assertStringContainsString('Preview', $withBanner);
        self::assertSame($html, PreviewBanner::strip($withBanner));
    }

    public function testInjectPlacesTheBannerImmediatelyAfterTheBodyTag(): void
    {
        $html = "<html><head><title>T</title></head><body><main>X</main></body></html>";

        $withBanner = PreviewBanner::inject($html);

        self::assertStringStartsWith('<html><head><title>T</title></head><body>', $withBanner);
        self::assertStringContainsString('<body>' . "\n" . '<!-- cuniform-preview-banner:start -->', $withBanner);
    }

    public function testStripIsANoOpOnHtmlWithNoBanner(): void
    {
        $html = '<html><body>plain</body></html>';

        self::assertSame($html, PreviewBanner::strip($html));
    }
}

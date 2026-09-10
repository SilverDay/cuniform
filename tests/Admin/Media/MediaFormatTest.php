<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Media;

use Cuniform\Admin\Media\MediaFormat;
use PHPUnit\Framework\TestCase;

final class MediaFormatTest extends TestCase
{
    public function testFromExtensionRecognisesEachAllowedFormatCaseInsensitively(): void
    {
        self::assertSame(MediaFormat::Jpeg, MediaFormat::fromExtension('jpg'));
        self::assertSame(MediaFormat::Jpeg, MediaFormat::fromExtension('JPEG'));
        self::assertSame(MediaFormat::Png, MediaFormat::fromExtension('PNG'));
        self::assertSame(MediaFormat::Webp, MediaFormat::fromExtension('webp'));
    }

    public function testFromExtensionRejectsAnythingNotAllowed(): void
    {
        self::assertNull(MediaFormat::fromExtension('gif'));
        self::assertNull(MediaFormat::fromExtension('svg'));
        self::assertNull(MediaFormat::fromExtension('php'));
        self::assertNull(MediaFormat::fromExtension(''));
    }

    public function testFromMimeTypeRecognisesEachAllowedFormat(): void
    {
        self::assertSame(MediaFormat::Jpeg, MediaFormat::fromMimeType('image/jpeg'));
        self::assertSame(MediaFormat::Png, MediaFormat::fromMimeType(' image/png '));
        self::assertSame(MediaFormat::Webp, MediaFormat::fromMimeType('image/webp'));
    }

    public function testFromMimeTypeRejectsAnythingNotAllowed(): void
    {
        self::assertNull(MediaFormat::fromMimeType('image/gif'));
        self::assertNull(MediaFormat::fromMimeType('image/svg+xml'));
        self::assertNull(MediaFormat::fromMimeType('application/octet-stream'));
    }

    public function testAllowedExtensionsListsEveryFormatsExtensions(): void
    {
        $extensions = MediaFormat::allowedExtensions();

        self::assertContains('jpg', $extensions);
        self::assertContains('jpeg', $extensions);
        self::assertContains('png', $extensions);
        self::assertContains('webp', $extensions);
        self::assertNotContains('gif', $extensions);
    }
}

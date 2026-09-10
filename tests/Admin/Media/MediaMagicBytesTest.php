<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Media;

use Cuniform\Admin\Media\MediaFormat;
use Cuniform\Admin\Media\MediaMagicBytes;
use PHPUnit\Framework\TestCase;

final class MediaMagicBytesTest extends TestCase
{
    public function testDetectsJpegBySignature(): void
    {
        self::assertSame(MediaFormat::Jpeg, MediaMagicBytes::detect("\xFF\xD8\xFF\xE0rest-of-header"));
    }

    public function testDetectsPngBySignature(): void
    {
        self::assertSame(MediaFormat::Png, MediaMagicBytes::detect("\x89PNG\x0D\x0A\x1A\x0A\x00\x00\x00\x0DIHDR"));
    }

    public function testDetectsWebpBySignature(): void
    {
        self::assertSame(MediaFormat::Webp, MediaMagicBytes::detect("RIFF\x24\x00\x00\x00WEBPVP8 "));
    }

    public function testReturnsNullForUnrecognisedBytes(): void
    {
        self::assertNull(MediaMagicBytes::detect('<?php echo "not an image"; ?>'));
        self::assertNull(MediaMagicBytes::detect(''));
        self::assertNull(MediaMagicBytes::detect("GIF89a\x01\x00\x01\x00"));
    }

    public function testReturnsNullForATruncatedHeaderTooShortToCarryTheWebpSignature(): void
    {
        self::assertNull(MediaMagicBytes::detect('RIFF'));
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Media;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Media\MediaFormat;
use Cuniform\Admin\Media\MediaReencoder;
use PHPUnit\Framework\TestCase;

final class MediaReencoderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cuniform_reencoder_' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $items = scandir($this->root);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    unlink($this->root . '/' . $item);
                }
            }
        }

        rmdir($this->root);
    }

    public function testReencodesAJpegAndReportsItsDimensions(): void
    {
        $path = $this->makeImage('source.jpg', MediaFormat::Jpeg, 40, 30);

        $result = (new MediaReencoder())->reencode($path, MediaFormat::Jpeg);

        self::assertSame(40, $result->width);
        self::assertSame(30, $result->height);
        self::assertSame(MediaFormat::Jpeg, $this->detectedMimeFormat($result->bytes));
    }

    public function testReencodesAPngPreservingDimensions(): void
    {
        $path = $this->makeImage('source.png', MediaFormat::Png, 12, 20);

        $result = (new MediaReencoder())->reencode($path, MediaFormat::Png);

        self::assertSame(12, $result->width);
        self::assertSame(20, $result->height);
        self::assertSame(MediaFormat::Png, $this->detectedMimeFormat($result->bytes));
    }

    public function testReencodesAWebpPreservingDimensions(): void
    {
        $path = $this->makeImage('source.webp', MediaFormat::Webp, 16, 16);

        $result = (new MediaReencoder())->reencode($path, MediaFormat::Webp);

        self::assertSame(16, $result->width);
        self::assertSame(16, $result->height);
        self::assertSame(MediaFormat::Webp, $this->detectedMimeFormat($result->bytes));
    }

    public function testReencodingStripsExifData(): void
    {
        $path = $this->root . '/with-exif.jpg';
        $image = imagecreatetruecolor(10, 10);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 100, 50));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        // Append a comment marker (APP1/EXIF-shaped enough for this test's
        // purpose: bytes that would survive a byte-for-byte copy but cannot
        // survive a decode/re-encode round trip through GD, since only
        // decoded pixel data reaches the output).
        file_put_contents($path, "Exif\x00\x00fake-exif-payload", FILE_APPEND);

        $result = (new MediaReencoder())->reencode($path, MediaFormat::Jpeg);

        self::assertStringNotContainsString('fake-exif-payload', $result->bytes);
    }

    public function testThrowsAdminExceptionForAFileThatIsNotAValidImage(): void
    {
        $path = $this->root . '/not-an-image.jpg';
        file_put_contents($path, 'this is not image data at all');

        $this->expectException(AdminException::class);

        (new MediaReencoder())->reencode($path, MediaFormat::Jpeg);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function makeImage(string $filename, MediaFormat $format, int $width, int $height): string
    {
        $path  = $this->root . '/' . $filename;
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 10, 150, 220));

        match ($format) {
            MediaFormat::Jpeg => imagejpeg($image, $path, 90),
            MediaFormat::Png => imagepng($image, $path, 6),
            MediaFormat::Webp => imagewebp($image, $path, 90),
        };
        imagedestroy($image);

        return $path;
    }

    private function detectedMimeFormat(string $bytes): ?MediaFormat
    {
        $size = getimagesizefromstring($bytes);
        \assert($size !== false);

        return MediaFormat::fromMimeType($size['mime']);
    }
}

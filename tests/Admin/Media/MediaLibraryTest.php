<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Media;

use Cuniform\Admin\Media\MediaLibrary;
use PHPUnit\Framework\TestCase;

final class MediaLibraryTest extends TestCase
{
    private string $contentRoot;

    protected function setUp(): void
    {
        $this->contentRoot = sys_get_temp_dir() . '/cuniform_medialib_' . uniqid();
        mkdir($this->contentRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->contentRoot);
    }

    public function testListIsEmptyWhenTheMediaDirectoryDoesNotExist(): void
    {
        self::assertSame([], (new MediaLibrary($this->contentRoot))->list());
    }

    public function testListsFilesWithSizeAndDimensions(): void
    {
        mkdir($this->contentRoot . '/media/2026/03', 0o755, true);
        $path = $this->contentRoot . '/media/2026/03/abc123.jpg';
        $image = imagecreatetruecolor(20, 10);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $entries = (new MediaLibrary($this->contentRoot))->list();

        self::assertCount(1, $entries);
        self::assertSame('2026/03/abc123.jpg', $entries[0]->relativePath);
        self::assertSame('/media/2026/03/abc123.jpg', $entries[0]->url);
        self::assertSame(20, $entries[0]->width);
        self::assertSame(10, $entries[0]->height);
        self::assertGreaterThan(0, $entries[0]->bytes);
    }

    public function testListsNewestFirst(): void
    {
        mkdir($this->contentRoot . '/media/2026/01', 0o755, true);

        $older = $this->contentRoot . '/media/2026/01/older.jpg';
        $newer = $this->contentRoot . '/media/2026/01/newer.jpg';
        file_put_contents($older, 'x');
        file_put_contents($newer, 'y');
        touch($older, time() - 100);
        touch($newer, time());

        $entries = (new MediaLibrary($this->contentRoot))->list();

        self::assertCount(2, $entries);
        self::assertSame('2026/01/newer.jpg', $entries[0]->relativePath);
        self::assertSame('2026/01/older.jpg', $entries[1]->relativePath);
    }

    public function testAFileThatIsNotADecodableImageIsListedWithZeroDimensionsRatherThanDropped(): void
    {
        mkdir($this->contentRoot . '/media', 0o755, true);
        file_put_contents($this->contentRoot . '/media/not-an-image.jpg', 'not actually an image');

        $entries = (new MediaLibrary($this->contentRoot))->list();

        self::assertCount(1, $entries);
        self::assertSame(0, $entries[0]->width);
        self::assertSame(0, $entries[0]->height);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_link($full) || !is_dir($full) ? unlink($full) : $this->removeDirectory($full);
        }

        rmdir($path);
    }
}

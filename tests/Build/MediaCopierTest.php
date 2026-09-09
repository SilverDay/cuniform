<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\MediaCopier;
use PHPUnit\Framework\TestCase;

final class MediaCopierTest extends TestCase
{
    private string $contentRoot;

    protected function setUp(): void
    {
        $this->contentRoot = sys_get_temp_dir() . '/cuniform_media_' . uniqid();
        mkdir($this->contentRoot . '/media/2026/03', 0o755, true);
        file_put_contents($this->contentRoot . '/media/2026/03/photo.jpg', 'bytes');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->contentRoot);
    }

    public function testListReturnsReleaseRelativePaths(): void
    {
        $paths = (new MediaCopier($this->contentRoot))->list();

        self::assertSame(['media/2026/03/photo.jpg'], $paths);
    }

    public function testListReturnsEmptyWhenNoMediaDirectoryExists(): void
    {
        $empty = sys_get_temp_dir() . '/cuniform_media_empty_' . uniqid();
        mkdir($empty, 0o755, true);

        self::assertSame([], (new MediaCopier($empty))->list());

        rmdir($empty);
    }

    public function testCopyIntoWritesTheFilesWithIdenticalContent(): void
    {
        $releaseDir = sys_get_temp_dir() . '/cuniform_media_release_' . uniqid();
        mkdir($releaseDir, 0o755, true);

        (new MediaCopier($this->contentRoot))->copyInto($releaseDir);

        self::assertFileExists($releaseDir . '/media/2026/03/photo.jpg');
        self::assertSame('bytes', file_get_contents($releaseDir . '/media/2026/03/photo.jpg'));

        $this->removeDirectory($releaseDir);
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
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\PublicDirectorySetup;
use PHPUnit\Framework\TestCase;

final class PublicDirectorySetupTest extends TestCase
{
    private string $root;
    private string $publicPath;

    protected function setUp(): void
    {
        $this->root       = sys_get_temp_dir() . '/cuniform_publicsetup_' . uniqid();
        $this->publicPath = $this->root . '/public';
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testDoesNothingWhenAlreadyASymlink(): void
    {
        $target = $this->root . '/releases/20260101000000';
        mkdir($target, 0o755, true);
        symlink($target, $this->publicPath);

        $message = (new PublicDirectorySetup($this->publicPath))->run();

        self::assertStringContainsString('already a symlink', $message);
        self::assertTrue(is_link($this->publicPath));
        self::assertSame($target, readlink($this->publicPath));
    }

    public function testDoesNothingWhenPublicDoesNotExistYet(): void
    {
        $message = (new PublicDirectorySetup($this->publicPath))->run();

        self::assertStringContainsString('does not exist yet', $message);
        self::assertFalse(file_exists($this->publicPath));
    }

    public function testRemovesAnEmptyRealDirectory(): void
    {
        mkdir($this->publicPath, 0o755, true);

        $message = (new PublicDirectorySetup($this->publicPath))->run();

        self::assertStringContainsString('empty directory', $message);
        self::assertFalse(file_exists($this->publicPath));
    }

    public function testMovesANonEmptyRealDirectoryAsideRatherThanDeletingIt(): void
    {
        mkdir($this->publicPath, 0o755, true);
        file_put_contents($this->publicPath . '/index.html', 'placeholder');

        $message = (new PublicDirectorySetup($this->publicPath))->run();

        self::assertStringContainsString('moved to', $message);
        self::assertStringContainsString('Nothing was deleted', $message);
        self::assertFalse(is_dir($this->publicPath), 'the original path must be clear for the next deploy');

        $backups = glob($this->publicPath . '.provisioned-*') ?: [];
        self::assertCount(1, $backups);
        self::assertFileExists($backups[0] . '/index.html');
        self::assertSame('placeholder', file_get_contents($backups[0] . '/index.html'));
    }

    private function removeDirectory(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }

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

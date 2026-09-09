<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\PageCountGuard;
use PHPUnit\Framework\TestCase;

final class PageCountGuardTest extends TestCase
{
    private string $releasesRoot;

    protected function setUp(): void
    {
        $this->releasesRoot = sys_get_temp_dir() . '/cuniform_pagecount_' . uniqid();
        mkdir($this->releasesRoot, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->releasesRoot);
    }

    public function testNoPreviousReleasePasses(): void
    {
        self::assertNull((new PageCountGuard($this->releasesRoot))->check(5, 0.10));
    }

    public function testASmallDropWithinThresholdPasses(): void
    {
        $this->makeRelease('20260101000000', 10);

        // 9% drop, under the 10% threshold.
        self::assertNull((new PageCountGuard($this->releasesRoot))->check(91, 0.10));
    }

    public function testADropOverTheThresholdIsAViolation(): void
    {
        $this->makeRelease('20260101000000', 100);

        $message = (new PageCountGuard($this->releasesRoot))->check(50, 0.10);

        self::assertNotNull($message);
        self::assertStringContainsString('page count dropped', $message);
    }

    public function testAnIncreaseNeverViolates(): void
    {
        $this->makeRelease('20260101000000', 10);

        self::assertNull((new PageCountGuard($this->releasesRoot))->check(100, 0.10));
    }

    public function testAssertThrowsWhenCheckReturnsAMessage(): void
    {
        $this->makeRelease('20260101000000', 100);

        $this->expectException(\Cuniform\Build\BuildException::class);
        (new PageCountGuard($this->releasesRoot))->assert(1, 0.10);
    }

    public function testComparesAgainstTheMostRecentReleaseWhenSeveralExist(): void
    {
        $this->makeRelease('20260101000000', 100);
        $this->makeRelease('20260201000000', 10);

        // Compared against the more recent (smaller) release, this should pass.
        self::assertNull((new PageCountGuard($this->releasesRoot))->check(9, 0.10));
    }

    private function makeRelease(string $timestamp, int $pageCount): void
    {
        $dir = $this->releasesRoot . '/' . $timestamp;
        mkdir($dir, 0o755, true);

        for ($i = 0; $i < $pageCount; $i++) {
            $pageDir = "{$dir}/page-{$i}";
            mkdir($pageDir, 0o755, true);
            file_put_contents("{$pageDir}/index.html", '<html></html>');
        }
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

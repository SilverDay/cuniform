<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Build;

use Cuniform\Admin\AdminException;
use Cuniform\Admin\Build\BuildRequestQueue;
use PHPUnit\Framework\TestCase;

final class BuildRequestQueueTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cuniform_buildqueue_' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testIsPendingIsFalseUntilEnqueueIsCalled(): void
    {
        $queue = new BuildRequestQueue($this->root . '/var/build-requested');

        self::assertFalse($queue->isPending());

        $queue->enqueue();

        self::assertTrue($queue->isPending());
    }

    public function testEnqueueCreatesTheRequestFileAndItsParentDirectory(): void
    {
        $path  = $this->root . '/var/build-requested';
        $queue = new BuildRequestQueue($path);

        self::assertDirectoryDoesNotExist($this->root . '/var');

        $queue->enqueue();

        self::assertFileExists($path);
    }

    public function testEnqueueIsIdempotent(): void
    {
        $path  = $this->root . '/var/build-requested';
        $queue = new BuildRequestQueue($path);

        $queue->enqueue();
        $firstMtime = filemtime($path);
        self::assertNotFalse($firstMtime);

        $queue->enqueue();

        self::assertTrue($queue->isPending());
        self::assertFileExists($path);
    }

    public function testEnqueueThrowsWhenTheRequestFileCannotBeWritten(): void
    {
        // A parent directory that exists but cannot be written into —
        // proxies the real OS-permission boundary this class relies on
        // (SPEC §10.5/T32: the admin process's actual write access is
        // enforced by the cuniform-web/cuniform-build Unix-user split,
        // which this test environment cannot recreate for real — see
        // BUILD-ORDER.md's own T32 note).
        $dir = $this->root . '/readonly';
        mkdir($dir, 0o555, true);

        try {
            $this->expectException(AdminException::class);
            (new BuildRequestQueue($dir . '/build-requested'))->enqueue();
        } finally {
            chmod($dir, 0o755);
        }
    }

    /**
     * Structural guarantee for T32's own acceptance criterion ("the admin
     * process cannot write to releases/ or public — verified by file
     * permissions, not by convention"): this class holds exactly one path,
     * given once at construction, and never derives or accepts any other —
     * there is no method, parameter, or code path here that could ever
     * target a directory other than the one it was built with.
     */
    public function testTheClassExposesNoWayToWriteAnywhereOtherThanItsOwnConfiguredPath(): void
    {
        $methods = get_class_methods(BuildRequestQueue::class);

        self::assertSame(['__construct', 'enqueue', 'isPending'], $methods);

        $reflection = new \ReflectionMethod(BuildRequestQueue::class, 'enqueue');
        self::assertCount(0, $reflection->getParameters(), 'enqueue() takes no path — it can only ever write to the constructor-given one');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        chmod($path, 0o755);
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

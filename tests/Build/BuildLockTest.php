<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\BuildLock;
use PHPUnit\Framework\TestCase;

final class BuildLockTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        $this->lockPath = sys_get_temp_dir() . '/cuniform_lock_' . uniqid() . '/build.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        @rmdir(dirname($this->lockPath));
    }

    public function testAcquireThenReleaseAllowsALaterAcquire(): void
    {
        $first = new BuildLock($this->lockPath);
        $first->acquire();
        $first->release();

        $second = new BuildLock($this->lockPath);
        $second->acquire();
        $second->release();

        $this->addToAssertionCount(1);
    }

    public function testASecondConcurrentBuildIsRejectedNotQueued(): void
    {
        $first = new BuildLock($this->lockPath);
        $first->acquire();

        try {
            $second = new BuildLock($this->lockPath);
            $this->expectException(BuildException::class);
            $this->expectExceptionMessageMatches('/already running/');
            $second->acquire();
        } finally {
            $first->release();
        }
    }

    public function testReleaseIsSafeToCallWithoutAcquiring(): void
    {
        (new BuildLock($this->lockPath))->release();

        $this->addToAssertionCount(1);
    }
}

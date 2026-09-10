<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\FileRecordStore;
use Cuniform\Admin\Auth\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cuniform_ratelimit_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testIsNotLockedBeforeAnyFailure(): void
    {
        self::assertFalse((new RateLimiter($this->directory))->isLocked('account:operator'));
    }

    public function testTheFirstThreeFailuresAreFree(): void
    {
        $limiter = new RateLimiter($this->directory);
        $limiter->recordFailure('account:operator');
        $limiter->recordFailure('account:operator');
        $limiter->recordFailure('account:operator');

        self::assertFalse($limiter->isLocked('account:operator'));
    }

    public function testTheFourthFailureLocksTheKey(): void
    {
        $limiter = new RateLimiter($this->directory);
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure('account:operator');
        }

        self::assertTrue($limiter->isLocked('account:operator'));
        self::assertGreaterThan(0, $limiter->retryAfterSeconds('account:operator'));
    }

    public function testBackoffGrowsWithMoreFailures(): void
    {
        $limiter = new RateLimiter($this->directory);
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure('account:operator');
        }
        $afterFour = $limiter->retryAfterSeconds('account:operator');

        for ($i = 0; $i < 3; $i++) {
            $limiter->recordFailure('account:operator');
        }
        $afterSeven = $limiter->retryAfterSeconds('account:operator');

        self::assertGreaterThan($afterFour, $afterSeven);
    }

    public function testRecordSuccessResetsTheCounter(): void
    {
        $limiter = new RateLimiter($this->directory);
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure('account:operator');
        }
        self::assertTrue($limiter->isLocked('account:operator'));

        $limiter->recordSuccess('account:operator');

        self::assertFalse($limiter->isLocked('account:operator'));
    }

    public function testDifferentKeysAreTrackedIndependently(): void
    {
        $limiter = new RateLimiter($this->directory);
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure('account:operator');
        }

        self::assertTrue($limiter->isLocked('account:operator'));
        self::assertFalse($limiter->isLocked('ip:203.0.113.5'));
    }

    public function testRetryAfterSecondsCountsDownAsTimePasses(): void
    {
        $limiter = new RateLimiter($this->directory);
        for ($i = 0; $i < 4; $i++) {
            $limiter->recordFailure('account:operator');
        }

        // Simulate elapsed time by back-dating the failure directly (no
        // injectable clock — same approach as PendingLoginStoreTest). At 4
        // failures the delay is 2s (base 1s * 2^(4-3)); back-dating by 1s
        // leaves roughly 1s remaining, strictly less than the full delay.
        $store = new FileRecordStore($this->directory);
        $data  = $store->read('account:operator');
        self::assertNotNull($data);
        $store->write('account:operator', ['failures' => $data['failures'], 'lastFailureAt' => time() - 1]);

        $remaining = $limiter->retryAfterSeconds('account:operator');
        self::assertGreaterThan(0, $remaining);
        self::assertLessThan(2, $remaining);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) && !is_link($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\FileRecordStore;
use Cuniform\Admin\Auth\PendingLoginStore;
use PHPUnit\Framework\TestCase;

final class PendingLoginStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cuniform_pendinglogins_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testCreateThenFindRoundTrips(): void
    {
        $store   = new PendingLoginStore($this->directory);
        $pending = $store->create('operator');

        $found = $store->find($pending->id);

        self::assertNotNull($found);
        self::assertSame($pending->id, $found->id);
        self::assertSame('operator', $found->username);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        self::assertNull((new PendingLoginStore($this->directory))->find('does-not-exist'));
    }

    public function testEachCreatedIdIsUnique(): void
    {
        $store = new PendingLoginStore($this->directory);

        self::assertNotSame($store->create('operator')->id, $store->create('operator')->id);
    }

    public function testDeleteRemovesThePendingLogin(): void
    {
        $store   = new PendingLoginStore($this->directory);
        $pending = $store->create('operator');
        $store->delete($pending->id);

        self::assertNull($store->find($pending->id));
    }

    public function testFindPurgesAndReturnsNullOnceTheTtlHasElapsed(): void
    {
        $store   = new PendingLoginStore($this->directory);
        $pending = $store->create('operator');

        // Back-date the record past the 5-minute TTL directly, since
        // PendingLoginStore has no injectable clock (php-style.md: no
        // static mutable state) — this writes through the same
        // FileRecordStore machinery PendingLoginStore itself uses.
        (new FileRecordStore($this->directory))->write($pending->id, [
            'username'  => 'operator',
            'createdAt' => time() - 301,
        ]);

        self::assertNull($store->find($pending->id));

        // The expired record is actually purged, not just hidden.
        self::assertNull((new FileRecordStore($this->directory))->read($pending->id));
    }

    public function testFindWithinTheTtlStillReturnsTheRecord(): void
    {
        $store   = new PendingLoginStore($this->directory);
        $pending = $store->create('operator');

        (new FileRecordStore($this->directory))->write($pending->id, [
            'username'  => 'operator',
            'createdAt' => time() - 299,
        ]);

        self::assertNotNull($store->find($pending->id));
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

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\FileRecordStore;
use Cuniform\Admin\Auth\SessionStore;
use PHPUnit\Framework\TestCase;

final class SessionStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cuniform_sessions_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testCreateThenFindRoundTrips(): void
    {
        $store   = new SessionStore($this->directory);
        $session = $store->create('operator');

        $found = $store->find($session->id);

        self::assertNotNull($found);
        self::assertSame($session->id, $found->id);
        self::assertSame('operator', $found->username);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        self::assertNull((new SessionStore($this->directory))->find('does-not-exist'));
    }

    public function testDestroyRemovesTheSession(): void
    {
        $store   = new SessionStore($this->directory);
        $session = $store->create('operator');
        $store->destroy($session->id);

        self::assertNull($store->find($session->id));
    }

    public function testRegenerateIssuesANewIdAndInvalidatesTheOldOne(): void
    {
        $store       = new SessionStore($this->directory);
        $session     = $store->create('operator');
        $regenerated = $store->regenerate($session);

        self::assertNotSame($session->id, $regenerated->id);
        self::assertSame('operator', $regenerated->username);
        self::assertSame($session->createdAt, $regenerated->createdAt);
        self::assertNull($store->find($session->id));
        self::assertNotNull($store->find($regenerated->id));
    }

    public function testFindPurgesASessionPastTheIdleTimeout(): void
    {
        $store   = new SessionStore($this->directory);
        $session = $store->create('operator');

        (new FileRecordStore($this->directory))->write($session->id, [
            'username'       => 'operator',
            'createdAt'      => time() - 100,
            'lastActivityAt' => time() - (30 * 60 + 1),
        ]);

        self::assertNull($store->find($session->id));
    }

    public function testFindPurgesASessionPastTheAbsoluteTimeoutEvenIfRecentlyActive(): void
    {
        $store   = new SessionStore($this->directory);
        $session = $store->create('operator');

        (new FileRecordStore($this->directory))->write($session->id, [
            'username'       => 'operator',
            'createdAt'      => time() - (12 * 60 * 60 + 1),
            'lastActivityAt' => time() - 5,
        ]);

        self::assertNull($store->find($session->id));
    }

    public function testFindRefreshesTheIdleWindow(): void
    {
        $store   = new SessionStore($this->directory);
        $session = $store->create('operator');

        (new FileRecordStore($this->directory))->write($session->id, [
            'username'       => 'operator',
            'createdAt'      => time() - 100,
            'lastActivityAt' => time() - 100,
        ]);

        $found = $store->find($session->id);
        self::assertNotNull($found);
        self::assertGreaterThan(time() - 5, $found->lastActivityAt);
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

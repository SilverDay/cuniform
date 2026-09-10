<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Auth;

use Cuniform\Admin\Auth\FileRecordStore;
use PHPUnit\Framework\TestCase;

final class FileRecordStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cuniform_filerecordstore_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testReadReturnsNullForAMissingRecord(): void
    {
        self::assertNull((new FileRecordStore($this->directory))->read('missing'));
    }

    public function testWriteThenReadRoundTrips(): void
    {
        $store = new FileRecordStore($this->directory);
        $store->write('key1', ['a' => 1, 'b' => 'two']);

        self::assertSame(['a' => 1, 'b' => 'two'], $store->read('key1'));
    }

    public function testWriteCreatesTheDirectoryIfMissing(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        (new FileRecordStore($this->directory))->write('key1', ['a' => 1]);

        self::assertDirectoryExists($this->directory);
    }

    public function testTheRawKeyNeverAppearsAsAFilename(): void
    {
        $store = new FileRecordStore($this->directory);
        $store->write('a-secret-session-id', ['a' => 1]);

        $files = glob($this->directory . '/*') ?: [];
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            self::assertStringNotContainsString('a-secret-session-id', basename($file));
        }
    }

    public function testDeleteRemovesTheRecord(): void
    {
        $store = new FileRecordStore($this->directory);
        $store->write('key1', ['a' => 1]);
        $store->delete('key1');

        self::assertNull($store->read('key1'));
    }

    public function testDeleteOfAMissingRecordIsSilent(): void
    {
        (new FileRecordStore($this->directory))->delete('never-written');

        $this->addToAssertionCount(1);
    }

    public function testWriteOverwritesAnExistingRecord(): void
    {
        $store = new FileRecordStore($this->directory);
        $store->write('key1', ['a' => 1]);
        $store->write('key1', ['a' => 2]);

        self::assertSame(['a' => 2], $store->read('key1'));
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

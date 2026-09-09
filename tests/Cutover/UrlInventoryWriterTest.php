<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cutover;

use Cuniform\Cutover\LegacyUrlEntry;
use Cuniform\Cutover\UrlInventory;
use Cuniform\Cutover\UrlInventoryWriter;
use Cuniform\Cutover\UrlSource;
use PHPUnit\Framework\TestCase;

final class UrlInventoryWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cuniform_inventory_' . uniqid() . '/legacy-urls.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
    }

    public function testWritesAReadableJsonInventory(): void
    {
        $inventory = new UrlInventory('https://legacy.test', [
            new LegacyUrlEntry('/post-a/', 200, 'text/html', UrlSource::Sitemap),
            new LegacyUrlEntry('/broken/', 404, 'text/html', UrlSource::Crawl),
        ]);

        (new UrlInventoryWriter())->write($inventory, $this->path);

        self::assertFileExists($this->path);
        $decoded = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('https://legacy.test', $decoded['baseUrl']);
        self::assertSame(2, $decoded['count']);
        self::assertSame('/post-a/', $decoded['entries'][0]['path']);
        self::assertSame(200, $decoded['entries'][0]['statusCode']);
        self::assertSame('sitemap', $decoded['entries'][0]['source']);
        self::assertSame(404, $decoded['entries'][1]['statusCode']);
        self::assertSame('crawl', $decoded['entries'][1]['source']);
    }

    public function testCreatesTheTargetDirectory(): void
    {
        self::assertDirectoryDoesNotExist(dirname($this->path));

        (new UrlInventoryWriter())->write(new UrlInventory('https://legacy.test', []), $this->path);

        self::assertDirectoryExists(dirname($this->path));
    }

    public function testNullStatusCodeAndContentTypeRoundTripAsJsonNull(): void
    {
        $inventory = new UrlInventory('https://legacy.test', [
            new LegacyUrlEntry('/from-sitemap/', null, null, UrlSource::Sitemap),
        ]);

        (new UrlInventoryWriter())->write($inventory, $this->path);

        $decoded = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNull($decoded['entries'][0]['statusCode']);
        self::assertNull($decoded['entries'][0]['contentType']);
    }
}

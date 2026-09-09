<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildCache;
use Cuniform\Build\CacheManifest;
use Cuniform\Build\CachedDocument;
use PHPUnit\Framework\TestCase;

final class BuildCacheTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cuniform_cache_' . uniqid() . '/build-cache.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
    }

    public function testMissingFileLoadsAsEmpty(): void
    {
        $manifest = (new BuildCache($this->path))->load();

        self::assertSame('', $manifest->navHash);
        self::assertSame([], $manifest->documents);
    }

    public function testSaveThenLoadRoundTrips(): void
    {
        $document = new CachedDocument(
            'basekey123',
            'translation-key',
            '<p>body</p>',
            [['level' => 2, 'id' => 'h', 'text' => 'Heading']],
            '<html>page</html>',
            '/en/a/',
        );
        $manifest = new CacheManifest('navhash', ['en:posts/en/a.md' => $document]);

        $cache = new BuildCache($this->path);
        $cache->save($manifest);
        $loaded = $cache->load();

        self::assertSame('navhash', $loaded->navHash);
        self::assertCount(1, $loaded->documents);
        $roundTripped = $loaded->documents['en:posts/en/a.md'];
        self::assertSame('basekey123', $roundTripped->baseKey);
        self::assertSame('translation-key', $roundTripped->translationKey);
        self::assertSame('<p>body</p>', $roundTripped->bodyHtml);
        self::assertSame([['level' => 2, 'id' => 'h', 'text' => 'Heading']], $roundTripped->headings);
        self::assertSame('<html>page</html>', $roundTripped->pageHtml);
        self::assertSame('/en/a/', $roundTripped->routePath);
    }

    public function testNullTranslationKeyRoundTrips(): void
    {
        $document = new CachedDocument('k', null, '', [], '', '/en/a/');
        $cache    = new BuildCache($this->path);
        $cache->save(new CacheManifest('nav', ['id' => $document]));

        self::assertNull($cache->load()->documents['id']->translationKey);
    }

    public function testCorruptJsonLoadsAsEmptyRatherThanFailing(): void
    {
        mkdir(dirname($this->path), 0o755, true);
        file_put_contents($this->path, '{not valid json');

        $manifest = (new BuildCache($this->path))->load();

        self::assertSame([], $manifest->documents);
    }

    public function testWrongShapeLoadsAsEmptyRatherThanFailing(): void
    {
        mkdir(dirname($this->path), 0o755, true);
        file_put_contents($this->path, json_encode(['unexpected' => true]));

        $manifest = (new BuildCache($this->path))->load();

        self::assertSame([], $manifest->documents);
    }
}

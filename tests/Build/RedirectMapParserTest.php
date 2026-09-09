<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\RedirectMapParser;
use Cuniform\Content\ContentException;
use PHPUnit\Framework\TestCase;

final class RedirectMapParserTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'cuniform_redirects_') . '.map';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testMissingFileParsesAsNoEntries(): void
    {
        self::assertSame([], (new RedirectMapParser())->parse($this->path . '.does-not-exist'));
    }

    public function testParsesValidEntriesSkippingBlankLinesAndComments(): void
    {
        file_put_contents($this->path, "# a comment\n\n/old/ /new/\n/feed.xml /de/feed.xml\n");

        $entries = (new RedirectMapParser())->parse($this->path);

        self::assertCount(2, $entries);
        self::assertSame('/old/', $entries[0]->oldPath);
        self::assertSame('/new/', $entries[0]->newPath);
        self::assertSame('/feed.xml', $entries[1]->oldPath);
        self::assertSame('/de/feed.xml', $entries[1]->newPath);
    }

    public function testMalformedLineIsAnError(): void
    {
        file_put_contents($this->path, "/old/ /new/ /extra/\n");

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/line 1/');
        (new RedirectMapParser())->parse($this->path);
    }

    public function testPathsMustStartWithASlash(): void
    {
        file_put_contents($this->path, "old/ /new/\n");

        $this->expectException(ContentException::class);
        $this->expectExceptionMessageMatches('/must start with/');
        (new RedirectMapParser())->parse($this->path);
    }

    public function testMultipleBadLinesAreReportedTogether(): void
    {
        file_put_contents($this->path, "bad-line\nold/ /new/\n");

        try {
            (new RedirectMapParser())->parse($this->path);
            self::fail('expected ContentException');
        } catch (ContentException $e) {
            self::assertStringContainsString('line 1', $e->getMessage());
            self::assertStringContainsString('line 2', $e->getMessage());
        }
    }
}

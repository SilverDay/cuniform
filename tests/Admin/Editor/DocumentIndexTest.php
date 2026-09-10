<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\Editor\DocumentIndex;
use PHPUnit\Framework\TestCase;

final class DocumentIndexTest extends TestCase
{
    private string $contentRoot;

    protected function setUp(): void
    {
        $this->contentRoot = sys_get_temp_dir() . '/cuniform_docindex_' . uniqid();
        mkdir($this->contentRoot . '/posts/en/2026', 0o755, true);

        file_put_contents(
            $this->contentRoot . '/posts/en/2026/2026-03-14-good.md',
            "---\ntitle: \"Good\"\nslug: \"good\"\nstatus: \"published\"\nsummary: \"S\"\ndate: \"2026-03-14T10:00:00+01:00\"\n---\nBody.",
        );
        file_put_contents(
            $this->contentRoot . '/posts/en/2026/2026-03-15-broken.md',
            "---\nslug: \"broken\"\nstatus: \"published\"\nsummary: \"S\"\ndate: \"2026-03-15T10:00:00+01:00\"\n---\nBody.",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->contentRoot);
    }

    public function testSummariesListsValidDocumentsAndCollectsErrorsForBrokenOnes(): void
    {
        $result = (new DocumentIndex($this->contentRoot, ['en']))->summaries();

        self::assertCount(1, $result['documents']);
        self::assertSame('good', $result['documents'][0]->slug);

        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('missing required key \'title\'', $result['errors'][0]);
    }

    public function testDiscoverReturnsEveryMarkdownFileRegardlessOfFrontMatterValidity(): void
    {
        $discovered = (new DocumentIndex($this->contentRoot, ['en']))->discover();

        self::assertCount(2, $discovered);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

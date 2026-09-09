<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\ImportException;
use Cuniform\Import\ReviewChecklistStore;
use Cuniform\Import\ReviewDecision;
use Cuniform\Import\ReviewEntry;
use PHPUnit\Framework\TestCase;

final class ReviewChecklistStoreTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cuniform_review_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }

    public function testLoadingAMissingFileReturnsAnEmptyChecklist(): void
    {
        self::assertSame([], (new ReviewChecklistStore())->load($this->tempDir . '/does-not-exist.json'));
    }

    public function testSaveThenLoadRoundTripsEveryField(): void
    {
        $path = $this->tempDir . '/review.json';

        $entries = [
            '11' => new ReviewEntry('11', 'posts/en/2020/2020-01-15-a-post.md', 'A Post', ['post_id=11: flagged'], ReviewDecision::Keep),
        ];

        (new ReviewChecklistStore())->save($path, $entries);
        $loaded = (new ReviewChecklistStore())->load($path);

        self::assertCount(1, $loaded);
        self::assertSame('11', $loaded['11']->sourceId);
        self::assertSame('posts/en/2020/2020-01-15-a-post.md', $loaded['11']->relativePath);
        self::assertSame('A Post', $loaded['11']->title);
        self::assertSame(['post_id=11: flagged'], $loaded['11']->flags);
        self::assertSame(ReviewDecision::Keep, $loaded['11']->decision);
    }

    public function testSaveCreatesTheParentDirectoryWhenMissing(): void
    {
        $path = $this->tempDir . '/nested/dir/review.json';

        (new ReviewChecklistStore())->save($path, ['1' => new ReviewEntry('1', 'a.md', 'A', [], ReviewDecision::Pending)]);

        self::assertFileExists($path);
    }

    public function testLoadingInvalidJsonThrows(): void
    {
        $path = $this->tempDir . '/broken.json';
        file_put_contents($path, '{not valid json');

        $this->expectException(ImportException::class);

        (new ReviewChecklistStore())->load($path);
    }

    public function testLoadingAnEntryWithAnUnknownDecisionValueThrows(): void
    {
        $path = $this->tempDir . '/bad-decision.json';
        file_put_contents($path, json_encode([
            '1' => ['relativePath' => 'a.md', 'title' => 'A', 'flags' => [], 'decision' => 'maybe'],
        ]));

        $this->expectException(ImportException::class);

        (new ReviewChecklistStore())->load($path);
    }

    public function testLoadingAnEntryMissingARequiredFieldThrows(): void
    {
        $path = $this->tempDir . '/incomplete.json';
        file_put_contents($path, json_encode([
            '1' => ['relativePath' => 'a.md', 'decision' => 'pending'],
        ]));

        $this->expectException(ImportException::class);

        (new ReviewChecklistStore())->load($path);
    }

    public function testLoadingAJsonArrayRatherThanAnObjectThrows(): void
    {
        $path = $this->tempDir . '/array.json';
        file_put_contents($path, '[1, 2, 3]');

        // A JSON array decodes to a PHP list with int keys — never a
        // valid source_id-keyed object, so this must fail the same way
        // any other malformed entry does.
        $this->expectException(ImportException::class);

        (new ReviewChecklistStore())->load($path);
    }
}

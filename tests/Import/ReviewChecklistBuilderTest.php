<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\ImportVerifier;
use Cuniform\Import\ReviewChecklistBuilder;
use Cuniform\Import\ReviewDecision;
use Cuniform\Import\ReviewEntry;
use Cuniform\Import\WxrChannel;
use Cuniform\Import\WxrDocument;
use Cuniform\Import\WxrImporter;
use Cuniform\Import\WxrItem;
use PHPUnit\Framework\TestCase;

final class ReviewChecklistBuilderTest extends TestCase
{
    public function testBuildProducesOneEntryPerImportedDocumentKeyedBySourceId(): void
    {
        $entries = $this->buildFor([
            $this->post(postId: 1, title: 'One', postName: 'one'),
            $this->post(postId: 2, title: 'Two', postName: 'two'),
        ]);

        self::assertCount(2, $entries);
        self::assertSame('One', $this->entry($entries, '1')->title);
        self::assertSame('Two', $this->entry($entries, '2')->title);
        self::assertSame(ReviewDecision::Pending, $this->entry($entries, '1')->decision);
    }

    public function testASkippedItemNeverAppearsInTheChecklist(): void
    {
        // private status -> skipped, never imported, nothing staged to review.
        $entries = $this->buildFor([$this->post(postId: 1, status: 'private')]);

        self::assertSame([], $entries);
    }

    public function testFlagsFromTheMigrationReportAreAttributedToTheirOwnDocument(): void
    {
        $entries = $this->buildFor([
            $this->post(postId: 1, postName: 'clean'),
            $this->post(postId: 2, postName: 'flagged', contentEncoded: '<p>[not_a_real_shortcode]</p>'),
        ]);

        self::assertSame([], $this->entry($entries, '1')->flags);
        self::assertNotSame([], $this->entry($entries, '2')->flags);
        self::assertStringContainsString('post_id=2', $this->entry($entries, '2')->flags[0]);
    }

    public function testSortedForReviewPutsFlaggedEntriesFirstAndIsStableOtherwise(): void
    {
        $entries = $this->buildFor([
            $this->post(postId: 1, postName: 'clean-a'),
            $this->post(postId: 2, postName: 'flagged', contentEncoded: '<p>[not_a_real_shortcode]</p>'),
            $this->post(postId: 3, postName: 'clean-b'),
        ]);

        $ordered = (new ReviewChecklistBuilder())->sortedForReview($entries);

        self::assertSame('2', $ordered[0]->sourceId);
        self::assertSame('1', $ordered[1]->sourceId);
        self::assertSame('3', $ordered[2]->sourceId);
    }

    public function testMergeCarriesAPriorDecisionForwardBySourceId(): void
    {
        $fresh    = $this->buildFor([$this->post(postId: 1, postName: 'one')]);
        $existing = $this->withDecisionFor($this->buildFor([$this->post(postId: 1, postName: 'one')]), '1', ReviewDecision::Keep);

        $merged = (new ReviewChecklistBuilder())->merge($fresh, $existing);

        self::assertSame(ReviewDecision::Keep, $this->entry($merged, '1')->decision);
    }

    public function testMergeStartsANewSourceIdAtPending(): void
    {
        $fresh    = $this->buildFor([$this->post(postId: 1, postName: 'one'), $this->post(postId: 2, postName: 'two')]);
        $existing = $this->withDecisionFor($this->buildFor([$this->post(postId: 1, postName: 'one')]), '1', ReviewDecision::Reject);

        $merged = (new ReviewChecklistBuilder())->merge($fresh, $existing);

        self::assertSame(ReviewDecision::Reject, $this->entry($merged, '1')->decision);
        self::assertSame(ReviewDecision::Pending, $this->entry($merged, '2')->decision);
    }

    public function testMergeDropsASourceIdNoLongerPresentInTheFreshBuild(): void
    {
        $fresh    = $this->buildFor([$this->post(postId: 1, postName: 'one')]);
        $existing = $this->buildFor([$this->post(postId: 1, postName: 'one'), $this->post(postId: 2, postName: 'two')]);

        $merged = (new ReviewChecklistBuilder())->merge($fresh, $existing);

        self::assertArrayNotHasKey('2', $merged);
    }

    public function testMergeAlwaysUsesTheFreshFlagsNeverTheStaleOnes(): void
    {
        $fresh = $this->buildFor([$this->post(postId: 1, postName: 'now-clean')]);

        $existingFlagged = $this->buildFor([$this->post(postId: 1, postName: 'now-clean', contentEncoded: '<p>[not_a_real_shortcode]</p>')]);
        $existing         = $this->withDecisionFor($existingFlagged, '1', ReviewDecision::Keep);

        $merged = (new ReviewChecklistBuilder())->merge($fresh, $existing);

        self::assertSame([], $this->entry($merged, '1')->flags);
        self::assertSame(ReviewDecision::Keep, $this->entry($merged, '1')->decision);
    }

    /**
     * @param list<WxrItem> $items
     *
     * @return array<int|string, ReviewEntry>
     */
    private function buildFor(array $items): array
    {
        $channel = new WxrChannel('Test', 'https://example.test', '', 'en-US', '1.2', 'https://example.test', 'https://example.test', []);
        $wxr     = new WxrDocument($channel, $items);
        $result  = (new WxrImporter('en'))->import($wxr);
        $report  = (new ImportVerifier())->verify($wxr, $result);

        return (new ReviewChecklistBuilder())->build($wxr, $result, $report);
    }

    /**
     * @param array<int|string, ReviewEntry> $entries
     */
    private function entry(array $entries, string $sourceId): ReviewEntry
    {
        foreach ($entries as $entry) {
            if ($entry->sourceId === $sourceId) {
                return $entry;
            }
        }

        self::fail("no entry for source_id={$sourceId}");
    }

    /**
     * @param array<int|string, ReviewEntry> $entries
     *
     * @return array<int|string, ReviewEntry>
     */
    private function withDecisionFor(array $entries, string $sourceId, ReviewDecision $decision): array
    {
        foreach ($entries as $key => $entry) {
            if ($entry->sourceId === $sourceId) {
                $entries[$key] = $entry->withDecision($decision);
            }
        }

        return $entries;
    }

    private function post(
        int $postId,
        string $title = 'Title',
        string $postName = 'title',
        string $status = 'publish',
        string $contentEncoded = '<p>Body.</p>',
    ): WxrItem {
        $link = "https://example.test/2020/01/{$postName}/";

        return new WxrItem(
            title: $title,
            link: $link,
            pubDate: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
            creator: 'author',
            guid: $link,
            description: '',
            contentEncoded: $contentEncoded,
            excerptEncoded: '',
            postId: $postId,
            postDate: '2020-01-15 10:00:00',
            postDateGmt: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
            postModified: '',
            postModifiedGmt: null,
            commentStatus: 'open',
            pingStatus: 'open',
            postName: $postName,
            status: $status,
            postParent: 0,
            menuOrder: 0,
            postType: 'post',
            postPassword: '',
            isSticky: false,
            attachmentUrl: null,
            categories: [],
            postmeta: [],
        );
    }
}

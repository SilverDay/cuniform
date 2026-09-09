<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\ImportException;
use Cuniform\Import\ImportVerifier;
use Cuniform\Import\WxrCategory;
use Cuniform\Import\WxrChannel;
use Cuniform\Import\WxrDocument;
use Cuniform\Import\WxrImporter;
use Cuniform\Import\WxrItem;
use PHPUnit\Framework\TestCase;

final class ImportVerifierTest extends TestCase
{
    public function testCleanImportProducesAnEmptyReportForEverySpecNamedBucket(): void
    {
        $report = $this->verify([$this->post(postId: 1)]);

        self::assertTrue($report->isClean());
        self::assertSame(1, $report->totalItems);
        self::assertSame(1, $report->importedCount);
        self::assertSame([], $report->unresolvedLegacyUrls);
        self::assertSame([], $report->wordCountOutliers);
        self::assertSame([], $report->unknownShortcodes);
        self::assertSame([], $report->unsupportedConstructs);
        self::assertSame([], $report->footnotes);
        self::assertSame([], $report->failedMediaDownloads);
        self::assertSame([], $report->manualDecisionItems);
        self::assertSame([], $report->otherNotices);
    }

    public function testCountsByStatusTallyEveryItemRegardlessOfWhetherItWasImported(): void
    {
        $report = $this->verify([
            $this->post(postId: 1, postName: 'one', status: 'publish'),
            $this->post(postId: 2, postName: 'two', status: 'publish'),
            $this->post(postId: 3, postName: 'three', status: 'draft'),
            $this->post(postId: 4, postName: 'four', status: 'trash'),
        ]);

        self::assertSame(['draft' => 1, 'publish' => 2, 'trash' => 1], $report->countsByStatus);
        self::assertSame(4, $report->totalItems);
        self::assertSame(3, $report->importedCount);
    }

    public function testTwoItemsResolvingToTheSameOutputPathIsAHardFailure(): void
    {
        // Same date and the same explicit slug -> the same relativePath.
        $items = [
            $this->post(postId: 1, postName: 'same-slug', postDateGmt: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC'))),
            $this->post(postId: 2, postName: 'same-slug', postDateGmt: new \DateTimeImmutable('2020-01-15 23:00:00', new \DateTimeZone('UTC'))),
        ];

        $this->expectException(ImportException::class);
        $this->expectExceptionMessageMatches('/same slug and date/');

        $this->verify($items);
    }

    public function testAPageItemWithARealPermalinkThatWasNotImportedIsAnUnresolvedLegacyUrl(): void
    {
        $report = $this->verify([$this->post(
            postId: 20,
            postType: 'page',
            link: 'https://example.test/about/',
        )]);

        self::assertCount(1, $report->unresolvedLegacyUrls);
        self::assertStringContainsString('post_id=20', $report->unresolvedLegacyUrls[0]);
        self::assertStringContainsString('/about/', $report->unresolvedLegacyUrls[0]);
        self::assertFalse($report->isClean());
    }

    public function testAQueryStringPermalinkThatWasNotImportedIsNotAnUnresolvedLegacyUrl(): void
    {
        // A private post never had a real indexed pretty-permalink here
        // (?p=1) — nothing for a redirect to preserve.
        $report = $this->verify([$this->post(postId: 1, status: 'private', link: 'https://example.test/?p=1')]);

        self::assertSame([], $report->unresolvedLegacyUrls);
    }

    public function testAnAttachmentWithARealLookingLinkIsNeverTreatedAsALegacyUrl(): void
    {
        $report = $this->verify([$this->attachment(postId: 11, url: 'https://example.test/2020/01/pic-jpg/', title: 'pic.jpg')]);

        self::assertSame([], $report->unresolvedLegacyUrls);
    }

    public function testAPostConvertedToRoughlyTheSameWordCountIsNotAnOutlier(): void
    {
        $report = $this->verify([$this->post(postId: 1, contentEncoded: '<p>One two three four five.</p>')]);

        self::assertSame([], $report->wordCountOutliers);
    }

    public function testAPostThatLostMostOfItsContentInConversionIsAWordCountOutlier(): void
    {
        // <script> is dropped entirely by the converter (SPEC §4.4) — the
        // paragraph survives, so most of the original word count vanishes.
        $long = str_repeat('word ', 40);
        $report = $this->verify([$this->post(postId: 1, title: 'Heavy Post', contentEncoded: "<script>{$long}</script><p>one two</p>")]);

        self::assertCount(1, $report->wordCountOutliers);
        self::assertStringContainsString('post_id=1', $report->wordCountOutliers[0]);
        self::assertStringContainsString('Heavy Post', $report->wordCountOutliers[0]);
        self::assertFalse($report->isClean());
    }

    public function testAnUnknownShortcodeIsCategorizedSeparatelyFromOtherWarnings(): void
    {
        $report = $this->verify([$this->post(postId: 1, contentEncoded: '<p>[not_a_real_shortcode]</p>')]);

        self::assertCount(1, $report->unknownShortcodes);
        self::assertStringContainsString('unknown shortcode', $report->unknownShortcodes[0]);
        self::assertSame([], $report->unsupportedConstructs);
    }

    public function testADroppedUnsupportedHtmlConstructIsCategorizedAsUnsupported(): void
    {
        $report = $this->verify([$this->post(postId: 1, contentEncoded: '<table><tr><td>cell</td></tr></table>')]);

        self::assertNotSame([], $report->unsupportedConstructs);
        self::assertSame([], $report->unknownShortcodes);
    }

    public function testAPrivateStatusItemIsAManualDecisionNotAnOtherNotice(): void
    {
        $report = $this->verify([$this->post(postId: 1, status: 'private')]);

        self::assertCount(1, $report->manualDecisionItems);
        self::assertStringContainsString('manual decision', $report->manualDecisionItems[0]);
        self::assertSame([], $report->otherNotices);
    }

    public function testAPageTypeItemIsAManualDecisionNotAnOtherNotice(): void
    {
        $report = $this->verify([$this->post(postId: 1, postType: 'page')]);

        self::assertCount(1, $report->manualDecisionItems);
    }

    public function testAnEmptySlugFallbackIsAnOtherNoticeNotAManualDecision(): void
    {
        $report = $this->verify([$this->post(postId: 1, postName: '')]);

        self::assertSame([], $report->manualDecisionItems);
        self::assertNotSame([], $report->otherNotices);
        self::assertStringContainsString('empty', $report->otherNotices[0]);
    }

    public function testRunningTheImportTwiceOnTheSameExportProducesByteIdenticalDocuments(): void
    {
        $items = [
            $this->post(postId: 1, title: 'One', categories: [$this->category('category', 'general', 'General')]),
            $this->post(postId: 2, title: 'Two', status: 'draft', postName: ''),
        ];

        $first  = $this->import($items);
        $second = $this->import($items);

        self::assertCount(2, $first['documents']);
        self::assertCount(2, $second['documents']);

        foreach ($first['documents'] as $index => $document) {
            self::assertSame($document->relativePath, $second['documents'][$index]->relativePath);
            self::assertSame($document->contents, $second['documents'][$index]->contents);
            self::assertSame($document->sourceId, $second['documents'][$index]->sourceId);
        }

        self::assertSame($first['warnings'], $second['warnings']);
    }

    /**
     * @param list<WxrItem> $items
     */
    private function verify(array $items): \Cuniform\Import\ImportReport
    {
        $wxr = $this->document($items);

        return (new ImportVerifier())->verify($wxr, (new WxrImporter('en'))->import($wxr));
    }

    /**
     * @param list<WxrItem> $items
     *
     * @return array{documents: list<\Cuniform\Import\ImportedDocument>, warnings: list<string>}
     */
    private function import(array $items): array
    {
        return (new WxrImporter('en'))->import($this->document($items));
    }

    /**
     * @param list<WxrItem> $items
     */
    private function document(array $items): WxrDocument
    {
        $channel = new WxrChannel('Test', 'https://example.test', '', 'en-US', '1.2', 'https://example.test', 'https://example.test', []);

        return new WxrDocument($channel, $items);
    }

    /**
     * @param list<WxrCategory> $categories
     */
    private function post(
        int $postId,
        string $title = 'Title',
        string $link = 'https://example.test/2020/01/15/title/',
        string $postName = 'title',
        string $status = 'publish',
        string $postType = 'post',
        string $contentEncoded = '<p>Body.</p>',
        string $excerptEncoded = '',
        ?\DateTimeImmutable $postDateGmt = new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
        array $categories = [],
    ): WxrItem {
        return new WxrItem(
            title: $title,
            link: $link,
            pubDate: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
            creator: 'author',
            guid: $link,
            description: '',
            contentEncoded: $contentEncoded,
            excerptEncoded: $excerptEncoded,
            postId: $postId,
            postDate: $postDateGmt?->format('Y-m-d H:i:s') ?? '',
            postDateGmt: $postDateGmt,
            postModified: '',
            postModifiedGmt: null,
            commentStatus: 'open',
            pingStatus: 'open',
            postName: $postName,
            status: $status,
            postParent: 0,
            menuOrder: 0,
            postType: $postType,
            postPassword: '',
            isSticky: false,
            attachmentUrl: null,
            categories: $categories,
            postmeta: [],
        );
    }

    private function attachment(int $postId, string $url, string $title): WxrItem
    {
        return new WxrItem(
            title: $title,
            link: $url,
            pubDate: null,
            creator: 'author',
            guid: $url,
            description: '',
            contentEncoded: '',
            excerptEncoded: '',
            postId: $postId,
            postDate: '',
            postDateGmt: null,
            postModified: '',
            postModifiedGmt: null,
            commentStatus: 'closed',
            pingStatus: 'closed',
            postName: '',
            status: 'inherit',
            postParent: 0,
            menuOrder: 0,
            postType: 'attachment',
            postPassword: '',
            isSticky: false,
            attachmentUrl: $url,
            categories: [],
            postmeta: [],
        );
    }

    private function category(string $domain, string $nicename, string $name): WxrCategory
    {
        return new WxrCategory($domain, $nicename, $name);
    }
}

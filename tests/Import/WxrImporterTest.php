<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\FrontMatterParser;
use Cuniform\Import\WxrCategory;
use Cuniform\Import\WxrChannel;
use Cuniform\Import\WxrDocument;
use Cuniform\Import\WxrImporter;
use Cuniform\Import\WxrItem;
use PHPUnit\Framework\TestCase;

final class WxrImporterTest extends TestCase
{
    public function testPublishedPostBecomesADocumentWithAllExpectedFields(): void
    {
        $item = $this->post(
            postId: 11,
            title: 'A Post Title',
            link: 'https://example.test/2020/01/a-post-title/',
            postName: 'a-post-title',
            status: 'publish',
            categories: [$this->category('category', 'general', 'General'), $this->category('post_tag', 'php', 'PHP')],
        );

        $result = $this->import([$item]);

        self::assertCount(1, $result['documents']);
        $doc = $result['documents'][0];

        self::assertSame('posts/en/2020/2020-01-15-a-post-title.md', $doc->relativePath);
        self::assertSame('11', $doc->sourceId);
        self::assertStringContainsString('title: "A Post Title"', $doc->contents);
        self::assertStringContainsString('slug: "a-post-title"', $doc->contents);
        self::assertStringContainsString('status: "published"', $doc->contents);
        self::assertStringContainsString('source_id: "11"', $doc->contents);
        self::assertStringContainsString("  - \"General\"\n  - \"PHP\"", $doc->contents);
        self::assertStringContainsString('aliases:', $doc->contents);
        self::assertStringContainsString('/2020/01/a-post-title/', $doc->contents);
    }

    public function testResultParsesCleanlyThroughTheRealFrontMatterParser(): void
    {
        $item   = $this->post(postId: 1, title: 'Title & <Things>', postName: 'title-things', status: 'publish');
        $result = $this->import([$item]);
        $doc    = $result['documents'][0];

        $frontMatter = (new FrontMatterParser())->parse($doc->contents, DocumentKind::Post, $doc->relativePath);

        self::assertSame('Title & <Things>', $frontMatter->shared->title);
        self::assertSame('title-things', $frontMatter->shared->slug);
    }

    public function testDraftAndPendingBothMapToDraft(): void
    {
        $draft   = $this->import([$this->post(postId: 1, status: 'draft')])['documents'][0];
        $pending = $this->import([$this->post(postId: 2, status: 'pending')])['documents'][0];

        self::assertStringContainsString('status: "draft"', $draft->contents);
        self::assertStringContainsString('status: "draft"', $pending->contents);
    }

    public function testFutureMapsToScheduled(): void
    {
        $doc = $this->import([$this->post(postId: 1, status: 'future')])['documents'][0];

        self::assertStringContainsString('status: "scheduled"', $doc->contents);
    }

    public function testPrivateStatusIsSkippedWithAWarning(): void
    {
        $result = $this->import([$this->post(postId: 1, status: 'private')]);

        self::assertSame([], $result['documents']);
        self::assertNotSame([], $result['warnings']);
        self::assertStringContainsString('private', $result['warnings'][0]);
    }

    public function testTrashAndAutoDraftAreSkippedSilently(): void
    {
        $result = $this->import([
            $this->post(postId: 1, status: 'trash'),
            $this->post(postId: 2, status: 'auto-draft'),
        ]);

        self::assertSame([], $result['documents']);
        self::assertSame([], $result['warnings']);
    }

    public function testUnrecognizedStatusIsSkippedWithAWarning(): void
    {
        $result = $this->import([$this->post(postId: 1, status: 'some-plugin-status')]);

        self::assertSame([], $result['documents']);
        self::assertStringContainsString('some-plugin-status', $result['warnings'][0]);
    }

    public function testRevisionAttachmentAndNavMenuItemAreSkippedSilently(): void
    {
        $items = [
            $this->post(postId: 1, postType: 'revision'),
            $this->post(postId: 2, postType: 'attachment'),
            $this->post(postId: 3, postType: 'nav_menu_item'),
        ];

        $result = $this->import($items);

        self::assertSame([], $result['documents']);
        self::assertSame([], $result['warnings']);
    }

    public function testPageIsSkippedWithAWarningNotSilently(): void
    {
        $result = $this->import([$this->post(postId: 1, postType: 'page')]);

        self::assertSame([], $result['documents']);
        self::assertNotSame([], $result['warnings']);
        self::assertStringContainsString('page', $result['warnings'][0]);
    }

    public function testUnrecognizedPostTypeIsSkippedWithAWarning(): void
    {
        $result = $this->import([$this->post(postId: 1, postType: 'product')]);

        self::assertSame([], $result['documents']);
        self::assertStringContainsString('product', $result['warnings'][0]);
    }

    public function testSlugIsUsedVerbatim(): void
    {
        $doc = $this->import([$this->post(postId: 1, postName: 'already-has-a-slug')])['documents'][0];

        self::assertStringContainsString('slug: "already-has-a-slug"', $doc->contents);
    }

    public function testEmptySlugFallsBackToATitleDerivedSlugWithAWarning(): void
    {
        $result = $this->import([$this->post(postId: 1, title: 'Ünusual Title!', postName: '')]);

        self::assertCount(1, $result['documents']);
        self::assertStringContainsString('slug: "uenusual-title"', $result['documents'][0]->contents);
        self::assertNotSame([], $result['warnings']);
        self::assertStringContainsString('empty', $result['warnings'][0]);
    }

    public function testInvalidSlugIsSkippedWithAWarning(): void
    {
        $result = $this->import([$this->post(postId: 1, postName: 'Has_Underscores_And_Caps')]);

        self::assertSame([], $result['documents']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testDateComesFromPostDateGmtInUtc(): void
    {
        $item = $this->post(postId: 1, postDateGmt: new \DateTimeImmutable('2012-04-18 08:42:51', new \DateTimeZone('UTC')));
        $doc  = $this->import([$item])['documents'][0];

        self::assertStringContainsString('date: "2012-04-18T08:42:51+00:00"', $doc->contents);
    }

    public function testDateFallsBackToPubDateWhenGmtDateIsMissingWithAWarning(): void
    {
        $item = $this->post(postId: 1, postDateGmt: null, pubDate: new \DateTimeImmutable('2018-12-26 15:14:30', new \DateTimeZone('UTC')));

        $result = $this->import([$item]);

        self::assertStringContainsString('date: "2018-12-26T15:14:30+00:00"', $result['documents'][0]->contents);
        self::assertNotSame([], $result['warnings']);
        self::assertStringContainsString('pubDate', $result['warnings'][0]);
    }

    public function testDocumentIsSkippedWhenNoUsableDateExistsAtAll(): void
    {
        $result = $this->import([$this->post(postId: 1, postDateGmt: null, pubDate: null)]);

        self::assertSame([], $result['documents']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testUpdatedIsOmittedWhenSameDayAsDate(): void
    {
        $item = $this->post(
            postId: 1,
            postDateGmt: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
            postModifiedGmt: new \DateTimeImmutable('2020-01-15 16:00:00', new \DateTimeZone('UTC')),
        );

        $doc = $this->import([$item])['documents'][0];

        self::assertStringNotContainsString('updated:', $doc->contents);
    }

    public function testUpdatedIsEmittedWhenOnADifferentDay(): void
    {
        $item = $this->post(
            postId: 1,
            postDateGmt: new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
            postModifiedGmt: new \DateTimeImmutable('2020-01-16 10:00:00', new \DateTimeZone('UTC')),
        );

        $doc = $this->import([$item])['documents'][0];

        self::assertStringContainsString('updated: "2020-01-16T10:00:00+00:00"', $doc->contents);
    }

    public function testCategoriesAndTagsAreMergedAndDeduplicatedCaseInsensitively(): void
    {
        $item = $this->post(postId: 1, categories: [
            $this->category('category', 'general', 'General'),
            $this->category('post_tag', 'general', 'general'),
            $this->category('post_tag', 'php', 'PHP'),
        ]);

        $doc = $this->import([$item])['documents'][0];

        self::assertStringContainsString("  - \"General\"\n  - \"PHP\"", $doc->contents);
    }

    public function testNonCategoryNonTagTaxonomiesAreIgnored(): void
    {
        $item = $this->post(postId: 1, categories: [$this->category('post_format', 'aside', 'Aside')]);

        $doc = $this->import([$item])['documents'][0];

        self::assertStringNotContainsString('tags:', $doc->contents);
    }

    public function testAliasIsOmittedForAQueryStringPermalink(): void
    {
        $doc = $this->import([$this->post(postId: 1, link: 'https://example.test/?p=1')])['documents'][0];

        self::assertStringNotContainsString('aliases:', $doc->contents);
    }

    public function testAliasIsTheSitePathOnlyNotTheFullUrl(): void
    {
        $doc = $this->import([$this->post(
            postId: 1,
            link: 'https://example.test/2020/01/a-post/',
            status: 'publish',
        )])['documents'][0];

        self::assertStringContainsString('- "/2020/01/a-post/"', $doc->contents);
        self::assertStringNotContainsString('example.test', $doc->contents);
    }

    public function testSummaryPrefersTheExcerptOverTheBody(): void
    {
        $item = $this->post(postId: 1, excerptEncoded: 'A short excerpt.', contentEncoded: '<p>A much longer body that would otherwise become the summary.</p>');

        $doc = $this->import([$item])['documents'][0];

        self::assertStringContainsString('summary: "A short excerpt."', $doc->contents);
    }

    public function testSummaryFallsBackToBodyAndTruncatesAtAWordBoundary(): void
    {
        $long = str_repeat('word ', 60); // well over 200 chars
        $item = $this->post(postId: 1, excerptEncoded: '', contentEncoded: "<p>{$long}</p>");

        $doc         = $this->import([$item])['documents'][0];
        $frontMatter = (new FrontMatterParser())->parse($doc->contents, DocumentKind::Post, $doc->relativePath);

        self::assertLessThanOrEqual(200, mb_strlen($frontMatter->shared->summary, 'UTF-8'));
        self::assertStringEndsWith('...', $frontMatter->shared->summary);
        self::assertStringNotContainsString(' ...', $frontMatter->shared->summary, 'must not truncate mid-word onto a trailing partial word');
    }

    public function testGalleryShortcodeResolvesAgainstThisImportsOwnAttachments(): void
    {
        $attachment = $this->attachment(postId: 99, url: 'https://example.test/media/pic.jpg', title: 'Pic');
        $post       = $this->post(postId: 1, contentEncoded: '<p>[gallery ids="99"]</p>');

        $result = $this->import([$post, $attachment]);

        self::assertCount(1, $result['documents']);
        self::assertStringContainsString('[figure src="https://example.test/media/pic.jpg" alt="Pic"]', $result['documents'][0]->contents);
    }

    /**
     * @param list<WxrItem> $items
     * @return array{documents: list<\Cuniform\Import\ImportedDocument>, warnings: list<string>}
     */
    private function import(array $items): array
    {
        $channel  = new WxrChannel('Test', 'https://example.test', '', 'en-US', '1.2', 'https://example.test', 'https://example.test', []);
        $document = new WxrDocument($channel, $items);

        return (new WxrImporter('en'))->import($document);
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
        ?\DateTimeImmutable $postModifiedGmt = null,
        ?\DateTimeImmutable $pubDate = new \DateTimeImmutable('2020-01-15 10:00:00', new \DateTimeZone('UTC')),
        array $categories = [],
    ): WxrItem {
        return new WxrItem(
            title: $title,
            link: $link,
            pubDate: $pubDate,
            creator: 'author',
            guid: $link,
            description: '',
            contentEncoded: $contentEncoded,
            excerptEncoded: $excerptEncoded,
            postId: $postId,
            postDate: $postDateGmt?->format('Y-m-d H:i:s') ?? '',
            postDateGmt: $postDateGmt,
            postModified: $postModifiedGmt?->format('Y-m-d H:i:s') ?? '',
            postModifiedGmt: $postModifiedGmt,
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

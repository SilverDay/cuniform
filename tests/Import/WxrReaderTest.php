<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\ImportException;
use Cuniform\Import\WxrItem;
use Cuniform\Import\WxrReader;
use PHPUnit\Framework\TestCase;

final class WxrReaderTest extends TestCase
{
    private const FIXTURE   = __DIR__ . '/../fixtures/Import/sample.xml';
    private const MALFORMED = __DIR__ . '/../fixtures/Import/malformed.xml';

    public function testParsesChannelMetadata(): void
    {
        $document = (new WxrReader())->parse(self::FIXTURE);

        self::assertSame('Example Blog', $document->channel->title);
        self::assertSame('https://example.test', $document->channel->link);
        self::assertSame('An example export for tests', $document->channel->description);
        self::assertSame('en-US', $document->channel->language);
        self::assertSame('1.2', $document->channel->wxrVersion);
        self::assertSame('https://example.test', $document->channel->baseSiteUrl);
        self::assertSame('https://example.test', $document->channel->baseBlogUrl);
    }

    public function testParsesAuthors(): void
    {
        $document = (new WxrReader())->parse(self::FIXTURE);

        self::assertCount(2, $document->channel->authors);
        $first = $document->channel->authors[0];
        self::assertSame(1, $first->id);
        self::assertSame('exampleauthor', $first->login);
        self::assertSame('author@example.test', $first->email);
        self::assertSame('Example Author', $first->displayName);
        self::assertSame('Example', $first->firstName);
        self::assertSame('Author', $first->lastName);
    }

    public function testParsesAPublishedPostWithCategoriesTagsAndPostmeta(): void
    {
        $post = $this->itemById(10);

        self::assertSame('A Published Post', $post->title);
        self::assertSame('https://example.test/2020/01/a-published-post/', $post->link);
        self::assertSame('exampleauthor', $post->creator);
        self::assertSame('<p>Hello world.</p>', $post->contentEncoded);
        self::assertSame('Hello excerpt.', $post->excerptEncoded);
        self::assertSame('a-published-post', $post->postName);
        self::assertSame('publish', $post->status);
        self::assertSame('post', $post->postType);
        self::assertTrue($post->isSticky);
        self::assertNull($post->attachmentUrl);

        self::assertNotNull($post->postDateGmt);
        self::assertSame('2020-01-15 10:00:00', $post->postDateGmt->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $post->postDateGmt->getTimezone()->getName());

        self::assertNotNull($post->pubDate);
        self::assertSame('2020-01-15T10:00:00+00:00', $post->pubDate->format('c'));

        self::assertCount(2, $post->categories);
        self::assertSame('category', $post->categories[0]->domain);
        self::assertSame('general', $post->categories[0]->nicename);
        self::assertSame('General', $post->categories[0]->name);
        self::assertSame('post_tag', $post->categories[1]->domain);
        self::assertSame('example-tag', $post->categories[1]->nicename);

        self::assertSame(['_edit_last' => ['1']], $post->postmeta);
    }

    public function testCommentsAreNotExposedOnTheItem(): void
    {
        // The fixture's post has a <wp:comment> child (SPEC §14.4/§A.6:
        // comments are never imported) — confirm it doesn't leak into
        // postmeta or otherwise change what the item reports.
        $post = $this->itemById(10);

        self::assertSame(['_edit_last' => ['1']], $post->postmeta);
    }

    public function testParsesAPageItem(): void
    {
        $page = $this->itemById(20);

        self::assertSame('page', $page->postType);
        self::assertSame('about', $page->postName);
        self::assertSame([], $page->categories);
        self::assertFalse($page->isSticky);
    }

    public function testParsesAnAttachmentItemWithItsUrl(): void
    {
        $attachment = $this->itemById(11);

        self::assertSame('attachment', $attachment->postType);
        self::assertSame('inherit', $attachment->status);
        self::assertSame(10, $attachment->postParent);
        self::assertSame('https://example.test/wp-content/uploads/2020/01/example-image.jpg', $attachment->attachmentUrl);
    }

    public function testDraftWithEmptySlugAndInvalidGmtDateParsesCleanly(): void
    {
        $draft = $this->itemById(30);

        self::assertSame('draft', $draft->status);
        self::assertSame('', $draft->postName);
        self::assertNull($draft->postDateGmt, '0000-00-00 00:00:00 must parse to null, not throw or produce a bogus date');
        self::assertSame('secondauthor', $draft->creator);
    }

    public function testMissingFileThrowsImportException(): void
    {
        $this->expectException(ImportException::class);
        (new WxrReader())->parse(self::FIXTURE . '.does-not-exist');
    }

    public function testMalformedXmlThrowsImportException(): void
    {
        $this->expectException(ImportException::class);
        (new WxrReader())->parse(self::MALFORMED);
    }

    public function testExternalEntityIsNotExpanded(): void
    {
        $path = sys_get_temp_dir() . '/cuniform_wxr_xxe_' . uniqid() . '.xml';
        file_put_contents($path, <<<'XML'
            <?xml version="1.0"?>
            <!DOCTYPE rss [
              <!ENTITY xxe SYSTEM "file:///etc/hostname">
            ]>
            <rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/">
            <channel>
                <title>XXE Attempt</title>
                <item>
                    <title>&xxe;</title>
                    <wp:post_id>1</wp:post_id>
                    <wp:post_type><![CDATA[post]]></wp:post_type>
                    <wp:status><![CDATA[publish]]></wp:status>
                </item>
            </channel>
            </rss>
            XML);

        try {
            $document = (new WxrReader())->parse($path);
            // Either the entity is left unexpanded (title contains the
            // literal, harmless "&xxe;" text) or the whole parse is
            // rejected outright (ImportException, caught below) — both
            // are safe outcomes. What must never happen is /etc/hostname's
            // actual contents ending up in the parsed title.
            self::assertStringNotContainsString("\n", $document->items[0]->title);
            self::assertNotSame((string) @file_get_contents('/etc/hostname'), trim($document->items[0]->title));
        } catch (ImportException) {
            self::addToAssertionCount(1);
        } finally {
            unlink($path);
        }
    }

    private function itemById(int $postId): WxrItem
    {
        $document = (new WxrReader())->parse(self::FIXTURE);

        foreach ($document->items as $item) {
            if ($item->postId === $postId) {
                return $item;
            }
        }

        self::fail("no item with post_id {$postId} in fixture");
    }
}

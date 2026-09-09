<?php

declare(strict_types=1);

namespace Cuniform\Tests\Import;

use Cuniform\Import\WpShortcodeConverter;
use Cuniform\Import\WxrItem;
use PHPUnit\Framework\TestCase;

final class WpShortcodeConverterTest extends TestCase
{
    public function testMapsCaptionToFigure(): void
    {
        $html = '[caption id="attachment_5" width="300"]<img src="http://x/a.jpg" alt="alt text" width="300" height="200" /> A nice caption[/caption]';

        $result = (new WpShortcodeConverter())->convert($html);

        self::assertSame(
            '[figure src="http://x/a.jpg" alt="alt text" caption="A nice caption" width="300" height="200"]',
            $result['html']
        );
        self::assertSame([], $result['warnings']);
    }

    public function testCaptionWithNoImgIsPreservedVerbatimAndReported(): void
    {
        $html = '[caption]Just text, no image[/caption]';

        $result = (new WpShortcodeConverter())->convert($html);

        self::assertSame($html, $result['html']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testDoubleQuoteInCaptionTextIsSanitizedNotEscaped(): void
    {
        $html = '[caption]<img src="a.jpg" alt="x"> He said "hello"[/caption]';

        $result = (new WpShortcodeConverter())->convert($html);

        self::assertStringContainsString("caption=\"He said 'hello'\"", $result['html']);
    }

    public function testMapsGalleryToRepeatedFigures(): void
    {
        $attachments = [
            1 => $this->attachment(1, 'https://x/one.jpg', 'One'),
            2 => $this->attachment(2, 'https://x/two.jpg', 'Two'),
        ];

        $result = (new WpShortcodeConverter($attachments))->convert('[gallery ids="1,2"]');

        self::assertSame(
            "[figure src=\"https://x/one.jpg\" alt=\"One\"]\n\n[figure src=\"https://x/two.jpg\" alt=\"Two\"]",
            $result['html']
        );
        self::assertSame([], $result['warnings']);
    }

    public function testGalleryUsesWpAttachmentImageAltMetaWhenPresent(): void
    {
        $attachment = $this->attachment(1, 'https://x/one.jpg', 'Fallback Title');
        $attachment = new WxrItem(
            $attachment->title,
            $attachment->link,
            $attachment->pubDate,
            $attachment->creator,
            $attachment->guid,
            $attachment->description,
            $attachment->contentEncoded,
            $attachment->excerptEncoded,
            $attachment->postId,
            $attachment->postDate,
            $attachment->postDateGmt,
            $attachment->postModified,
            $attachment->postModifiedGmt,
            $attachment->commentStatus,
            $attachment->pingStatus,
            $attachment->postName,
            $attachment->status,
            $attachment->postParent,
            $attachment->menuOrder,
            $attachment->postType,
            $attachment->postPassword,
            $attachment->isSticky,
            $attachment->attachmentUrl,
            $attachment->categories,
            ['_wp_attachment_image_alt' => ['Real Alt Text']],
        );

        $result = (new WpShortcodeConverter([1 => $attachment]))->convert('[gallery ids="1"]');

        self::assertStringContainsString('alt="Real Alt Text"', $result['html']);
    }

    public function testGalleryWithUnresolvableIdsIsPreservedVerbatimAndReported(): void
    {
        $result = (new WpShortcodeConverter())->convert('[gallery ids="99"]');

        self::assertSame('[gallery ids="99"]', $result['html']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testMapsYoutubeEmbed(): void
    {
        $result = (new WpShortcodeConverter())->convert('[embed]https://www.youtube.com/watch?v=abc123[/embed]');

        self::assertSame('[embed provider="youtube" id="abc123"]', $result['html']);
        self::assertSame([], $result['warnings']);
    }

    public function testMapsYoutubeShortUrlEmbed(): void
    {
        $result = (new WpShortcodeConverter())->convert('[embed]https://youtu.be/abc123[/embed]');

        self::assertSame('[embed provider="youtube" id="abc123"]', $result['html']);
    }

    public function testMapsVimeoEmbed(): void
    {
        $result = (new WpShortcodeConverter())->convert('[embed]https://vimeo.com/123456[/embed]');

        self::assertSame('[embed provider="vimeo" id="123456"]', $result['html']);
    }

    public function testUnsupportedEmbedProviderIsPreservedVerbatimAndReported(): void
    {
        $html   = '[embed]https://example.com/video/1[/embed]';
        $result = (new WpShortcodeConverter())->convert($html);

        self::assertSame($html, $result['html']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testUnknownShortcodeIsPreservedVerbatimAndReportedOncePerName(): void
    {
        $html   = 'Text [myplugin foo="bar"] more [myplugin foo="baz"] text.';
        $result = (new WpShortcodeConverter())->convert($html);

        self::assertSame($html, $result['html']);
        self::assertCount(1, $result['warnings'], 'the same unknown shortcode name is only reported once');
        self::assertStringContainsString('myplugin', $result['warnings'][0]);
    }

    public function testCuniformsOwnShortcodeNamesAreNeverReportedAsUnknown(): void
    {
        $result = (new WpShortcodeConverter())->convert('[note type="info"]Hello[/note] and [toc]');

        self::assertSame([], $result['warnings']);
    }

    public function testFigureEmittedFromACaptionIsNotReportedAsUnknown(): void
    {
        $html   = '[caption]<img src="a.jpg" alt="x"> cap[/caption]';
        $result = (new WpShortcodeConverter())->convert($html);

        self::assertSame([], $result['warnings']);
    }

    public function testClosingTagsAreNotFlaggedAsUnknownShortcodes(): void
    {
        // A stray, already-handled closing tag left behind must not be
        // misread as its own unknown opening shortcode.
        $result = (new WpShortcodeConverter())->convert('plain text [/notreallyaclosingtag]');

        self::assertSame([], $result['warnings']);
    }

    private function attachment(int $id, string $url, string $title): WxrItem
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
            postId: $id,
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
}

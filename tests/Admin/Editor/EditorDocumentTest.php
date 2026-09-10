<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Editor;

use Cuniform\Admin\Editor\EditorDocument;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\LegalRole;
use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use PHPUnit\Framework\TestCase;

final class EditorDocumentTest extends TestCase
{
    public function testFromParsedFlattensAPostsSharedAndPostOnlyFields(): void
    {
        $shared = new SharedFrontMatter(
            title: 'A Post',
            slug: 'a-post',
            status: DocumentStatus::Published,
            summary: 'Summary',
            translationKey: 'key-1',
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: ['/old-path/'],
            toc: false,
            sourceId: null,
        );
        $frontMatter = new PostFrontMatter(
            shared: $shared,
            date: new \DateTimeImmutable('2026-03-14T10:00:00+01:00'),
            tags: ['a', 'b'],
            series: 'my-series',
            body: 'Body text.',
        );

        $doc = EditorDocument::fromParsed('posts/en/2026/2026-03-14-a-post.md', 'en', DocumentKind::Post, 'sha', $frontMatter);

        self::assertSame('a-post', $doc->slug);
        self::assertSame(['a', 'b'], $doc->tags);
        self::assertSame('my-series', $doc->series);
        self::assertNull($doc->template);
        self::assertSame(['/old-path/'], $doc->aliases);
    }

    public function testFromParsedFlattensAPagesSharedAndPageOnlyFields(): void
    {
        $shared = new SharedFrontMatter(
            title: 'A Page',
            slug: 'a-page',
            status: DocumentStatus::Draft,
            summary: 'Summary',
            translationKey: null,
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: true,
            sourceId: null,
        );
        $frontMatter = new PageFrontMatter(
            shared: $shared,
            template: 'page.php',
            navLabel: 'A Page',
            navOrder: 3,
            navParent: null,
            navGroup: NavGroup::Primary,
            sitemapPriority: 0.5,
            legal: LegalRole::Privacy,
            body: 'Body text.',
        );

        $doc = EditorDocument::fromParsed('pages/en/a-page.md', 'en', DocumentKind::Page, 'sha', $frontMatter);

        self::assertNull($doc->date);
        self::assertSame([], $doc->tags);
        self::assertSame(3, $doc->navOrder);
        self::assertSame(NavGroup::Primary, $doc->navGroup);
        self::assertSame(0.5, $doc->sitemapPriority);
        self::assertSame(LegalRole::Privacy, $doc->legal);
    }

    public function testAsSaveRequestFormatsDatesAsIso8601WithOffset(): void
    {
        $shared = new SharedFrontMatter(
            title: 'A Post',
            slug: 'a-post',
            status: DocumentStatus::Published,
            summary: 'Summary',
            translationKey: null,
            updated: new \DateTimeImmutable('2026-03-15T08:00:00+01:00'),
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: false,
            sourceId: null,
        );
        $frontMatter = new PostFrontMatter(
            shared: $shared,
            date: new \DateTimeImmutable('2026-03-14T10:00:00+01:00'),
            tags: [],
            series: null,
            body: 'Body.',
        );
        $doc = EditorDocument::fromParsed('posts/en/2026/2026-03-14-a-post.md', 'en', DocumentKind::Post, 'sha', $frontMatter);

        $request = $doc->asSaveRequest('de', 'ein-beitrag');

        self::assertNull($request->identifier);
        self::assertNull($request->expectedSha256);
        self::assertSame('de', $request->language);
        self::assertSame('ein-beitrag', $request->slug);
        self::assertSame('2026-03-14T10:00:00+01:00', $request->date);
        self::assertSame('2026-03-15T08:00:00+01:00', $request->updated);
    }
}

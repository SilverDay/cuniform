<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\FeedGenerator;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\ResolvedSite;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use Cuniform\Render\RenderedDocument;
use PHPUnit\Framework\TestCase;

final class FeedGeneratorTest extends TestCase
{
    public function testOnlyTheLatestFeedItemsAreIncluded(): void
    {
        $posts = [
            $this->post('a', '2026-01-01'),
            $this->post('b', '2026-02-01'),
            $this->post('c', '2026-03-01'),
        ];

        $files = $this->generate($posts, feedItems: 2);
        $rss   = $this->contentsOf($files, 'feed.xml');

        self::assertStringContainsString('/de/c/', $rss);
        self::assertStringContainsString('/de/b/', $rss);
        self::assertStringNotContainsString('/de/a/', $rss, 'only the latest feed_items posts belong in the feed');
    }

    public function testPagesNeverAppearInFeeds(): void
    {
        $files = $this->generate([$this->post('a', '2026-01-01'), $this->page('impressum')], feedItems: 20);
        $rss   = $this->contentsOf($files, 'feed.xml');

        self::assertStringNotContainsString('Impressum', $rss);
    }

    public function testDeclaresTheFeedLanguage(): void
    {
        $files = $this->generate([$this->post('a', '2026-01-01')], feedItems: 20);
        $rss   = $this->contentsOf($files, 'feed.xml');

        self::assertStringContainsString('<language>de</language>', $rss);
    }

    public function testNoFeedIsWrittenForALanguageWithNoPublishedPosts(): void
    {
        $files = $this->generate([], feedItems: 20, languages: ['de', 'en']);

        self::assertSame([], $files);
    }

    /**
     * @param  list<ResolvedDocument> $documents
     * @param  list<string>           $languages
     * @return list<\Cuniform\Build\ArtifactFile>
     */
    private function generate(array $documents, int $feedItems, array $languages = ['de']): array
    {
        $renderedByIdentifier = [];
        foreach ($documents as $document) {
            $renderedByIdentifier[$document->parsed->identifier()] = new RenderedDocument(
                $document->parsed->frontMatter,
                '<p>Body <img src="/media/x.jpg"></p>',
                []
            );
        }

        $config = ConfigFixture::make(languages: $languages, feedItems: $feedItems);
        $site   = new ResolvedSite($documents, [], []);

        return (new FeedGenerator($config))->generate($site, $renderedByIdentifier);
    }

    /**
     * @param list<\Cuniform\Build\ArtifactFile> $files
     */
    private function contentsOf(array $files, string $suffix): string
    {
        foreach ($files as $file) {
            if (str_ends_with($file->relativePath, $suffix)) {
                return $file->contents;
            }
        }

        self::fail("no artifact ending in {$suffix}");
    }

    private function post(string $slug, string $date): ResolvedDocument
    {
        $shared      = $this->sharedFrontMatter($slug);
        $frontMatter = new PostFrontMatter($shared, new \DateTimeImmutable($date), [], null, '<p>Body.</p>');
        $relativePath = "posts/de/2026/{$date}-{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, 'de', 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);

        return new ResolvedDocument($parsed, "/de/{$slug}/", null);
    }

    private function page(string $slug): ResolvedDocument
    {
        $shared      = $this->sharedFrontMatter($slug, title: 'Impressum');
        $frontMatter = new PageFrontMatter($shared, 'page.php', null, null, null, null, null, null, '<p>Body.</p>');
        $relativePath = "pages/de/{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Page, 'de', 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);

        return new ResolvedDocument($parsed, "/de/{$slug}/", null);
    }

    private function sharedFrontMatter(string $slug, string $title = ''): SharedFrontMatter
    {
        return new SharedFrontMatter(
            title: $title !== '' ? $title : ucfirst($slug),
            slug: $slug,
            status: DocumentStatus::Published,
            summary: 'Summary.',
            translationKey: null,
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: [],
            toc: false,
            sourceId: null,
        );
    }
}

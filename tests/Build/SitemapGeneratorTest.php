<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\ResolvedSite;
use Cuniform\Build\SitemapGenerator;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use PHPUnit\Framework\TestCase;

final class SitemapGeneratorTest extends TestCase
{
    public function testExcludesNoindexDocuments(): void
    {
        $visible = $this->resolvedPost('visible', noindex: false);
        $hidden  = $this->resolvedPost('hidden', noindex: true);

        $xml = $this->generate([$visible, $hidden]);

        self::assertStringContainsString('/de/visible/', $xml);
        self::assertStringNotContainsString('/de/hidden/', $xml);
    }

    public function testLastmodPrefersUpdatedOverDate(): void
    {
        $document = $this->resolvedPost('with-update', updated: new \DateTimeImmutable('2026-05-01'));

        $xml = $this->generate([$document]);

        self::assertStringContainsString('<lastmod>2026-05-01</lastmod>', $xml);
    }

    public function testLastmodFallsBackToDate(): void
    {
        $document = $this->resolvedPost('without-update');

        $xml = $this->generate([$document]);

        self::assertStringContainsString('<lastmod>2026-03-14</lastmod>', $xml);
    }

    /**
     * @param list<ResolvedDocument> $documents
     */
    private function generate(array $documents): string
    {
        $site      = new ResolvedSite($documents, [], []);
        $generator = new SitemapGenerator(ConfigFixture::make());

        return $generator->generate($site)->contents;
    }

    private function resolvedPost(string $slug, bool $noindex = false, ?\DateTimeImmutable $updated = null): ResolvedDocument
    {
        $shared = new SharedFrontMatter(
            title: ucfirst($slug),
            slug: $slug,
            status: DocumentStatus::Published,
            summary: 'Summary.',
            translationKey: null,
            updated: $updated,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: $noindex,
            aliases: [],
            toc: false,
            sourceId: null,
        );

        $frontMatter = new PostFrontMatter($shared, new \DateTimeImmutable('2026-03-14'), [], null, '<p>Body.</p>');
        $relativePath = "posts/de/2026/2026-03-14-{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, 'de', 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);

        return new ResolvedDocument($parsed, "/de/{$slug}/", null);
    }
}

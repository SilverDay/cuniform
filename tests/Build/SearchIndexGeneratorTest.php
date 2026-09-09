<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\ResolvedSite;
use Cuniform\Build\SearchIndexGenerator;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use Cuniform\Render\RenderedDocument;
use PHPUnit\Framework\TestCase;

final class SearchIndexGeneratorTest extends TestCase
{
    public function testEntryShapeAndPlainTextBody(): void
    {
        [$document, $rendered] = $this->postFixture('sicherheitskultur', '<p>Kultur <strong>schlägt</strong> Compliance.</p>');

        [$file, $warnings] = $this->generate([$document], [$rendered], 750 * 1024);

        $entries = json_decode($file->contents, true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $entries);
        self::assertSame('/de/sicherheitskultur/', $entries[0]['path']);
        self::assertSame('de', $entries[0]['lang']);
        self::assertSame('post', $entries[0]['type']);
        self::assertSame(['awareness'], $entries[0]['tags']);
        self::assertSame('2026-03-14', $entries[0]['date']);
        self::assertSame('Kultur schlägt Compliance.', $entries[0]['body_plain']);
        self::assertSame([], $warnings);
    }

    public function testWarnsAboveTheConfiguredByteThreshold(): void
    {
        [$document, $rendered] = $this->postFixture('long-post', '<p>' . str_repeat('word ', 50) . '</p>');

        [, $warnings] = $this->generate([$document], [$rendered], 10);

        self::assertNotSame([], $warnings);
        self::assertStringContainsString('search-index.json', $warnings[0]);
        self::assertStringContainsString('byte threshold', $warnings[0]);
    }

    /**
     * @return array{0: ResolvedDocument, 1: RenderedDocument}
     */
    private function postFixture(string $slug, string $bodyHtml): array
    {
        $shared = new SharedFrontMatter(
            title: ucfirst($slug),
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

        $frontMatter  = new PostFrontMatter($shared, new \DateTimeImmutable('2026-03-14'), ['awareness'], null, $bodyHtml);
        $relativePath = "posts/de/2026/2026-03-14-{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, 'de', 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);
        $resolved     = new ResolvedDocument($parsed, "/de/{$slug}/", null);
        $rendered     = new RenderedDocument($frontMatter, $bodyHtml, []);

        return [$resolved, $rendered];
    }

    /**
     * @param  list<ResolvedDocument>   $documents
     * @param  list<RenderedDocument>   $renderedInOrder
     * @return array{0: \Cuniform\Build\ArtifactFile, 1: list<string>}
     */
    private function generate(array $documents, array $renderedInOrder, int $warnBytes): array
    {
        $renderedByIdentifier = [];
        foreach ($documents as $i => $document) {
            $renderedByIdentifier[$document->parsed->identifier()] = $renderedInOrder[$i];
        }

        $generator = new SearchIndexGenerator(ConfigFixture::make(searchIndexWarnBytes: $warnBytes));
        $site      = new ResolvedSite($documents, [], []);

        return $generator->generate($site, $renderedByIdentifier);
    }
}

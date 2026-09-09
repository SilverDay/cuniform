<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\RedirectEntry;
use Cuniform\Build\RedirectMapCompiler;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\ResolvedSite;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use PHPUnit\Framework\TestCase;

final class RedirectMapCompilerTest extends TestCase
{
    public function testCombinesAliasAndManualEntries(): void
    {
        $site   = $this->siteWithAliasedPost(['/old-path/']);
        $manual = [new RedirectEntry('/feed.xml', '/de/feed.xml', 'content/redirects.map')];

        $result = (new RedirectMapCompiler())->compile($site, $manual);

        self::assertCount(2, $result['entries']);
        self::assertSame([], $result['warnings']);
    }

    public function testDropsARootEntryWithAWarning(): void
    {
        $site   = new ResolvedSite([], [], []);
        $manual = [new RedirectEntry('/', '/en/', 'content/redirects.map')];

        $result = (new RedirectMapCompiler())->compile($site, $manual);

        self::assertSame([], $result['entries']);
        self::assertNotSame([], $result['warnings']);
    }

    public function testTwoEntriesClaimingTheSameOldPathIsABuildError(): void
    {
        $site   = $this->siteWithAliasedPost(['/dup/']);
        $manual = [new RedirectEntry('/dup/', '/somewhere-else/', 'content/redirects.map')];

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/claimed by both/');
        (new RedirectMapCompiler())->compile($site, $manual);
    }

    /**
     * @param list<string> $aliases
     */
    private function siteWithAliasedPost(array $aliases): ResolvedSite
    {
        $shared = new SharedFrontMatter(
            title: 'Sicherheitskultur',
            slug: 'sicherheitskultur',
            status: DocumentStatus::Published,
            summary: 'Summary.',
            translationKey: null,
            updated: null,
            image: null,
            imageAlt: null,
            canonical: null,
            noindex: false,
            aliases: $aliases,
            toc: false,
            sourceId: null,
        );

        $frontMatter  = new PostFrontMatter($shared, new \DateTimeImmutable('2026-03-14'), [], null, '<p>Body.</p>');
        $relativePath = 'posts/de/2026/2026-03-14-sicherheitskultur.md';
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, 'de', 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);
        $resolved     = new ResolvedDocument($parsed, '/de/sicherheitskultur/', null);

        return new ResolvedSite([$resolved], [], []);
    }
}

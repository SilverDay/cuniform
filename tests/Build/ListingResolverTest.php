<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\ListingResolver;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\ResolvedDocument;
use Cuniform\Build\ResolvedSite;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use Cuniform\I18n\DateFormatter;
use Cuniform\I18n\UiStringCatalogue;
use PHPUnit\Framework\TestCase;

final class ListingResolverTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/cuniform_listing_lang_' . uniqid();
        mkdir($this->langDir, 0o755, true);
        $months = '';
        for ($m = 1; $m <= 12; $m++) {
            $months .= sprintf("    'month_%02d' => 'M%d',\n", $m, $m);
        }
        file_put_contents($this->langDir . '/en.php', "<?php\nreturn [\n{$months}];\n");
        file_put_contents($this->langDir . '/de.php', "<?php\nreturn [\n{$months}];\n");
    }

    protected function tearDown(): void
    {
        unlink($this->langDir . '/en.php');
        unlink($this->langDir . '/de.php');
        rmdir($this->langDir);
    }

    public function testPostsAreReverseChronological(): void
    {
        $site = new ResolvedSite([
            $this->post('older', '2026-01-01', 'en'),
            $this->post('newer', '2026-06-01', 'en'),
        ], [], []);

        $listing = $this->resolve($site);

        self::assertSame(['newer', 'older'], array_map(static fn ($p) => $p->title, $listing->postsByLanguage['en']));
    }

    public function testTagsAreLanguageScoped(): void
    {
        $site = new ResolvedSite([
            $this->post('de-post', '2026-01-01', 'de', tags: ['awareness']),
            $this->post('en-post', '2026-01-01', 'en', tags: ['awareness']),
        ], [], []);

        $listing = $this->resolve($site);

        self::assertCount(1, $listing->tagsByLanguage['de']);
        self::assertCount(1, $listing->tagsByLanguage['en']);
        self::assertCount(1, $listing->tagsByLanguage['de'][0]->posts);
    }

    public function testDifferentlyCasedTagsShareOneArchiveByTheirSlug(): void
    {
        // Posts are grouped in the order postsByLanguage already presents
        // them (reverse-chronological) — so the *newer* post's spelling
        // becomes the label, not insertion order in this array literal.
        $site = new ResolvedSite([
            $this->post('a', '2026-02-01', 'en', tags: ['Awareness']),
            $this->post('b', '2026-01-01', 'en', tags: ['awareness']),
        ], [], []);

        $tags = $this->resolve($site)->tagsByLanguage['en'];

        self::assertCount(1, $tags);
        self::assertSame('awareness', $tags[0]->slug);
        self::assertSame('Awareness', $tags[0]->label, 'newest post\'s spelling becomes the label');
        self::assertCount(2, $tags[0]->posts);
    }

    public function testTagUrlIsPrecomputedAndLanguagePrefixed(): void
    {
        $site = new ResolvedSite([$this->post('a', '2026-01-01', 'en', tags: ['awareness'])], [], []);

        $post = $this->resolve($site)->postsByLanguage['en'][0];

        self::assertSame('/en/tag/awareness/', $post->tags[0]['url']);
    }

    public function testSeriesGroupsBySlugAndStaysReverseChronological(): void
    {
        $site = new ResolvedSite([
            $this->post('part-1', '2026-01-01', 'en', series: 'Onboarding'),
            $this->post('part-2', '2026-02-01', 'en', series: 'Onboarding'),
        ], [], []);

        $series = $this->resolve($site)->seriesByLanguage['en'];

        self::assertCount(1, $series);
        self::assertSame('onboarding', $series[0]->slug);
        self::assertSame(['part-2', 'part-1'], array_map(static fn ($p) => $p->title, $series[0]->posts));
    }

    public function testPostsWithoutASeriesAreExcludedFromSeriesArchives(): void
    {
        $site = new ResolvedSite([$this->post('a', '2026-01-01', 'en')], [], []);

        self::assertSame([], $this->resolve($site)->seriesByLanguage['en']);
    }

    public function testYearArchivesGroupByPublicationYear(): void
    {
        $site = new ResolvedSite([
            $this->post('a', '2025-06-01', 'en'),
            $this->post('b', '2026-01-01', 'en'),
            $this->post('c', '2026-06-01', 'en'),
        ], [], []);

        $years = $this->resolve($site)->yearsByLanguage['en'];
        $byYear = [];
        foreach ($years as $year) {
            $byYear[$year->year] = count($year->posts);
        }
        ksort($byYear);

        self::assertSame([2025 => 1, 2026 => 2], $byYear);
    }

    private function resolve(ResolvedSite $site): \Cuniform\Build\ListingSet
    {
        $strings   = UiStringCatalogue::load($this->langDir, ['de', 'en']);
        $formatter = new DateFormatter($strings, forceFallback: true);

        return (new ListingResolver(ConfigFixture::make(languages: ['de', 'en']), $formatter))->resolve($site);
    }

    /**
     * @param list<string> $tags
     */
    private function post(string $slug, string $date, string $language, array $tags = [], ?string $series = null): ResolvedDocument
    {
        $shared = new SharedFrontMatter(
            title: $slug,
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

        $frontMatter  = new PostFrontMatter($shared, new \DateTimeImmutable($date), $tags, $series, '<p>Body.</p>');
        $relativePath = "posts/{$language}/{$slug}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, $language, 0, 'x');
        $parsed       = new ParsedDocument($discovered, $frontMatter);

        return new ResolvedDocument($parsed, "/{$language}/{$slug}/", null);
    }
}

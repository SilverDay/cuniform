<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\ListingSet;
use Cuniform\Build\ListingTemplateStage;
use Cuniform\Build\PostSummary;
use Cuniform\Build\SeriesArchive;
use Cuniform\Build\TagArchive;
use Cuniform\Build\YearArchive;
use Cuniform\Config\UrlPrefix;
use Cuniform\I18n\UiStringCatalogue;
use Cuniform\Template\TemplateResolver;
use PHPUnit\Framework\TestCase;

final class ListingTemplateStageTest extends TestCase
{
    private const REAL_TEMPLATES = __DIR__ . '/../../templates';
    private const LANG_DIR       = __DIR__ . '/../../config/lang';

    public function testIndexPageOneAndPageTwoBothRenderWithCorrectPagination(): void
    {
        $posts = [$this->summary('a'), $this->summary('b'), $this->summary('c')];
        $listing = new ListingSet(['en' => $posts], ['en' => []], ['en' => []], ['en' => []]);

        $files = $this->stage(['en'], postsPerPage: 2)->build($listing, []);

        $page1 = $this->find($files, '/en/');
        self::assertStringContainsString('a</a>', $page1->html);
        self::assertStringContainsString('b</a>', $page1->html);
        self::assertStringNotContainsString('c</a>', $page1->html);
        self::assertStringContainsString('/en/page/2/', $page1->html, 'page 1 must link to the next page');

        $page2 = $this->find($files, '/en/page/2/');
        self::assertStringContainsString('c</a>', $page2->html);
        self::assertStringNotContainsString('pagination-next', $page2->html, 'the last page has no next link');
    }

    public function testEmptyLanguageStillProducesAHomeRoute(): void
    {
        $listing = new ListingSet(['en' => []], ['en' => []], ['en' => []], ['en' => []]);

        $files = $this->stage(['en'])->build($listing, []);

        $this->find($files, '/en/');
        $this->addToAssertionCount(1);
    }

    public function testTagArchiveRendersItsOwnPostsOnly(): void
    {
        $listing = new ListingSet(
            ['en' => []],
            ['en' => [new TagArchive('awareness', 'Awareness', [$this->summary('a')])]],
            ['en' => []],
            ['en' => []],
        );

        $files = $this->stage(['en'])->build($listing, []);
        $tagPage = $this->find($files, '/en/tag/awareness/');

        self::assertStringContainsString('Awareness', $tagPage->html);
        self::assertStringContainsString('a</a>', $tagPage->html);
    }

    public function testSeriesPageIsNotPaginated(): void
    {
        $posts = array_map(fn (int $i) => $this->summary("p{$i}"), range(1, 5));
        $listing = new ListingSet(
            ['en' => []],
            ['en' => []],
            ['en' => [new SeriesArchive('onboarding', 'Onboarding', $posts)]],
            ['en' => []],
        );

        $files = $this->stage(['en'])->build($listing, []);
        $seriesPage = $this->find($files, '/en/series/onboarding/');

        foreach ($posts as $post) {
            self::assertStringContainsString("{$post->title}</a>", $seriesPage->html);
        }
    }

    public function testArchiveYearPage(): void
    {
        $listing = new ListingSet(
            ['en' => []],
            ['en' => []],
            ['en' => []],
            ['en' => [new YearArchive(2026, [$this->summary('a')])]],
        );

        $files = $this->stage(['en'])->build($listing, []);
        $archivePage = $this->find($files, '/en/archive/2026/');

        self::assertStringContainsString('2026', $archivePage->html);
    }

    public function testSearchPageLinksTheFingerprintedScript(): void
    {
        $listing = new ListingSet(['en' => []], ['en' => []], ['en' => []], ['en' => []]);

        $files = $this->stage(['en'])->build($listing, []);
        $searchPage = $this->find($files, '/en/search/');

        self::assertStringContainsString('/search.deadbeef.js', $searchPage->html);
    }

    public function testPerLanguage404ExistsWhenPrefixed(): void
    {
        $listing = new ListingSet(['en' => []], ['en' => []], ['en' => []], ['en' => []]);

        $files = $this->stage(['en'], urlPrefix: UrlPrefix::Always)->build($listing, []);

        $this->find($files, '/en/404.html');
        $this->addToAssertionCount(1);
    }

    public function testPerLanguage404IsAbsentWhenUnprefixed(): void
    {
        $listing = new ListingSet(['en' => []], ['en' => []], ['en' => []], ['en' => []]);

        $files = $this->stage(['en'], urlPrefix: UrlPrefix::Never)->build($listing, []);

        self::assertNotContains('/en/404.html', array_map(static fn ($f) => $f->routePath, $files));
        // The neutral root 404.html is unaffected — it's not per-language.
        $this->find($files, '/404.html');
    }

    public function testNeutralRootErrorPageListsEveryConfiguredLanguage(): void
    {
        $listing = new ListingSet(
            ['de' => [], 'en' => []],
            ['de' => [], 'en' => []],
            ['de' => [], 'en' => []],
            ['de' => [], 'en' => []],
        );

        $files = $this->stage(['de', 'en'])->build($listing, []);
        $neutral = $this->find($files, '/404.html');

        self::assertStringContainsString('lang="de"', $neutral->html);
        self::assertStringContainsString('lang="en"', $neutral->html);
        self::assertStringContainsString('/de/', $neutral->html);
        self::assertStringContainsString('/en/', $neutral->html);
    }

    private function summary(string $title): PostSummary
    {
        return new PostSummary($title, "/en/{$title}/", 'Summary.', new \DateTimeImmutable('2026-01-01'), '1 January 2026', [], null);
    }

    /**
     * @param list<string> $languages
     */
    private function stage(array $languages, int $postsPerPage = 10, UrlPrefix $urlPrefix = UrlPrefix::Always): ListingTemplateStage
    {
        $config  = ConfigFixture::make(languages: $languages, urlPrefix: $urlPrefix, postsPerPage: $postsPerPage);
        $strings = UiStringCatalogue::load(self::LANG_DIR, $languages);

        return new ListingTemplateStage(
            $config,
            new TemplateResolver(self::REAL_TEMPLATES),
            $strings,
            '/style.deadbeef.css',
            '/search.deadbeef.js',
        );
    }

    /**
     * @param list<\Cuniform\Build\GeneratedFile> $files
     */
    private function find(array $files, string $routePath): \Cuniform\Build\GeneratedFile
    {
        foreach ($files as $file) {
            if ($file->routePath === $routePath) {
                return $file;
            }
        }

        self::fail("no generated file for route '{$routePath}'");
    }

}

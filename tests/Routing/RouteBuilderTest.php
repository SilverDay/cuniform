<?php

declare(strict_types=1);

namespace Cuniform\Tests\Routing;

use Cuniform\Config\UrlPrefix;
use Cuniform\Routing\RouteBuilder;
use PHPUnit\Framework\TestCase;

final class RouteBuilderTest extends TestCase
{
    public function testPrefixAlwaysPrefixesEvenWithOneLanguage(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['de'], '/{slug}/');

        self::assertSame('de/', $builder->prefixFor('de'));
    }

    public function testPrefixAutoPrefixesWithMultipleLanguages(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Auto, ['de', 'en'], '/{slug}/');

        self::assertSame('de/', $builder->prefixFor('de'));
        self::assertSame('en/', $builder->prefixFor('en'));
    }

    public function testPrefixAutoDoesNotPrefixWithOneLanguage(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Auto, ['de'], '/{slug}/');

        self::assertSame('', $builder->prefixFor('de'));
    }

    public function testPrefixNeverNeverPrefixes(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Never, ['de'], '/{slug}/');

        self::assertSame('', $builder->prefixFor('de'));
    }

    public function testPostRouteWithDefaultPermalinkUnderAlways(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['de', 'en'], '/{slug}/');

        [$path, $firstSegment] = $builder->postRoute('de', 'sicherheitskultur', new \DateTimeImmutable('2026-03-14'));

        self::assertSame('/de/sicherheitskultur/', $path);
        self::assertSame('sicherheitskultur', $firstSegment);
    }

    public function testPostRouteWithDefaultPermalinkUnderNever(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Never, ['de'], '/{slug}/');

        [$path] = $builder->postRoute('de', 'sicherheitskultur', new \DateTimeImmutable('2026-03-14'));

        self::assertSame('/sicherheitskultur/', $path);
    }

    public function testPostRouteWithDateTokens(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{year}/{month}/{day}/{slug}/');

        [$path, $firstSegment] = $builder->postRoute('en', 'security-culture', new \DateTimeImmutable('2026-03-14'));

        self::assertSame('/en/2026/03/14/security-culture/', $path);
        self::assertSame('2026', $firstSegment);
    }

    public function testPageRouteMirrorsTheDirectoryTree(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['de', 'en'], '/{slug}/');

        [$path, $firstSegment] = $builder->pageRoute('de', 'vortraege/coffee-factor');

        self::assertSame('/de/vortraege/coffee-factor/', $path);
        self::assertSame('vortraege', $firstSegment);
    }

    public function testTopLevelPageRoute(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['de', 'en'], '/{slug}/');

        [$path, $firstSegment] = $builder->pageRoute('de', 'impressum');

        self::assertSame('/de/impressum/', $path);
        self::assertSame('impressum', $firstSegment);
    }

    public function testIndexRoutePageOneIsTheLanguageHome(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['de', 'en'], '/{slug}/');

        self::assertSame('/en/', $builder->indexRoute('en', 1));
    }

    public function testIndexRoutePageTwoAndBeyondUsesThePageSuffix(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/page/2/', $builder->indexRoute('en', 2));
        self::assertSame('/en/page/7/', $builder->indexRoute('en', 7));
    }

    public function testIndexRouteUnderNeverHasNoLanguageSegment(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Never, ['en'], '/{slug}/');

        self::assertSame('/', $builder->indexRoute('en', 1));
        self::assertSame('/page/2/', $builder->indexRoute('en', 2));
    }

    public function testTagRoute(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/tag/awareness/', $builder->tagRoute('en', 'awareness', 1));
        self::assertSame('/en/tag/awareness/page/2/', $builder->tagRoute('en', 'awareness', 2));
    }

    public function testSeriesRouteIsNeverPaginated(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/series/onboarding/', $builder->seriesRoute('en', 'onboarding'));
    }

    public function testArchiveRoute(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/archive/2026/', $builder->archiveRoute('en', 2026));
    }

    public function testSearchRoute(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/search/', $builder->searchRoute('en'));
    }

    public function testErrorRoute(): void
    {
        $builder = new RouteBuilder(UrlPrefix::Always, ['en'], '/{slug}/');

        self::assertSame('/en/404.html', $builder->errorRoute('en'));
    }
}

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
}

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\RoutePathResolver;
use PHPUnit\Framework\TestCase;

final class RoutePathResolverTest extends TestCase
{
    public function testTrailingSlashRouteGetsIndexHtml(): void
    {
        self::assertSame('de/sicherheitskultur/index.html', RoutePathResolver::toReleaseFilePath('/de/sicherheitskultur/'));
    }

    public function testRootResolvesToBareIndexHtml(): void
    {
        self::assertSame('index.html', RoutePathResolver::toReleaseFilePath('/'));
    }

    public function testNonTrailingSlashPathIsUsedAsIs(): void
    {
        self::assertSame('media/2026/03/photo.jpg', RoutePathResolver::toReleaseFilePath('/media/2026/03/photo.jpg'));
        self::assertSame('sitemap.xml', RoutePathResolver::toReleaseFilePath('/sitemap.xml'));
    }
}

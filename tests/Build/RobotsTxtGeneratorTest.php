<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\RobotsTxtGenerator;
use PHPUnit\Framework\TestCase;

final class RobotsTxtGeneratorTest extends TestCase
{
    public function testDisallowsAdminAndPointsAtTheSitemap(): void
    {
        $file = (new RobotsTxtGenerator(ConfigFixture::make()))->generate();

        self::assertSame('robots.txt', $file->relativePath);
        self::assertStringContainsString('Disallow: /admin', $file->contents);
        self::assertStringContainsString('Sitemap: https://blog.silverday.de/sitemap.xml', $file->contents);
    }
}

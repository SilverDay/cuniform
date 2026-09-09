<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\UrlSchemeGuard;
use Cuniform\Config\UrlPrefix;
use PHPUnit\Framework\TestCase;

final class UrlSchemeGuardTest extends TestCase
{
    private string $metaPath;

    protected function setUp(): void
    {
        $this->metaPath = sys_get_temp_dir() . '/cuniform_scheme_' . uniqid() . '/last-build-meta.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->metaPath);
        @rmdir(dirname($this->metaPath));
    }

    public function testFirstBuildEverPasses(): void
    {
        self::assertNull((new UrlSchemeGuard($this->metaPath))->check(ConfigFixture::make(), false));
    }

    public function testUnchangedSchemePasses(): void
    {
        $guard  = new UrlSchemeGuard($this->metaPath);
        $config = ConfigFixture::make(languages: ['de', 'en']);
        $guard->persist($config);

        self::assertNull($guard->check($config, false));
    }

    public function testChangedDefaultLanguageWithoutTheFlagIsAViolation(): void
    {
        $guard = new UrlSchemeGuard($this->metaPath);
        $guard->persist(ConfigFixture::make(languages: ['de', 'en'], defaultLanguage: 'en'));

        $changed = ConfigFixture::make(languages: ['de', 'en'], defaultLanguage: 'de');
        $message = $guard->check($changed, false);

        self::assertNotNull($message);
        self::assertStringContainsString('default_language', $message);
        self::assertStringContainsString('RedirectMatch 302', $message);
    }

    public function testChangedUrlPrefixWithoutTheFlagIsAViolation(): void
    {
        $guard = new UrlSchemeGuard($this->metaPath);
        $guard->persist(ConfigFixture::make(languages: ['de'], urlPrefix: UrlPrefix::Never));

        $message = $guard->check(ConfigFixture::make(languages: ['de'], urlPrefix: UrlPrefix::Always), false);

        self::assertNotNull($message);
        self::assertStringContainsString('url_prefix', $message);
        self::assertStringContainsString("'never' -> 'always'", $message);
    }

    public function testChangedSchemeWithTheFlagIsAllowed(): void
    {
        $guard = new UrlSchemeGuard($this->metaPath);
        $guard->persist(ConfigFixture::make(languages: ['de']));

        self::assertNull($guard->check(ConfigFixture::make(languages: ['de', 'en']), true));
    }

    public function testLanguageOrderDoesNotMatter(): void
    {
        $guard = new UrlSchemeGuard($this->metaPath);
        $guard->persist(ConfigFixture::make(languages: ['de', 'en'], defaultLanguage: 'de'));

        self::assertNull($guard->check(ConfigFixture::make(languages: ['en', 'de'], defaultLanguage: 'de'), false));
    }

    public function testAssertThrowsWhenCheckReturnsAMessage(): void
    {
        $guard = new UrlSchemeGuard($this->metaPath);
        $guard->persist(ConfigFixture::make(languages: ['de']));

        $this->expectException(BuildException::class);
        $guard->assert(ConfigFixture::make(languages: ['de', 'en']), false);
    }
}

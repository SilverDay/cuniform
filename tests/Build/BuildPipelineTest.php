<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\BuildLock;
use Cuniform\Build\BuildOptions;
use Cuniform\Build\BuildPipeline;
use Cuniform\Config\BuildSettings;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigPaths;
use Cuniform\Config\MailSettings;
use Cuniform\Config\UrlPrefix;
use PHPUnit\Framework\TestCase;

final class BuildPipelineTest extends TestCase
{
    private const FIXTURE_CONTENT = __DIR__ . '/../fixtures/Build/content';
    private const REAL_TEMPLATES  = __DIR__ . '/../../templates';
    private const LANG_DIR        = __DIR__ . '/../../config/lang';

    private string $scratchDir;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir() . '/cuniform_build_' . uniqid();
        mkdir($this->scratchDir . '/releases', 0o755, true);
        mkdir($this->scratchDir . '/var', 0o755, true);
        mkdir($this->scratchDir . '/public', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->scratchDir);
    }

    public function testFullBuildWritesEveryExpectedRouteWithCorrectContent(): void
    {
        $pipeline = new BuildPipeline($this->config(self::REAL_TEMPLATES), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions());

        self::assertNotNull($result->releaseDir);
        self::assertSame(5, $result->documentCount, 'draft and not-yet-due scheduled posts must be excluded');

        $dePost = $this->read($result->releaseDir, '/de/sicherheitskultur/');
        self::assertStringContainsString('<h1>Sicherheitskultur</h1>', $dePost);
        self::assertStringContainsString('<figcaption>Beispielbild</figcaption>', $dePost);
        self::assertStringContainsString('Gebaut mit Cuniform.', $dePost, '[include] must inline the target page body');
        self::assertStringContainsString('hreflang="en"', $dePost);
        self::assertStringContainsString('hreflang="x-default"', $dePost);
        self::assertStringContainsString('14. März 2026', $dePost);
        self::assertStringContainsString('<li>awareness</li>', $dePost);

        $enPost = $this->read($result->releaseDir, '/en/security-culture/');
        self::assertStringContainsString('<h1>Security Culture</h1>', $enPost);
        self::assertStringContainsString('hreflang="de"', $enPost);
        self::assertStringContainsString('14 March 2026', $enPost);

        $navPage = $this->read($result->releaseDir, '/de/vortraege/');
        self::assertStringContainsString('<h1>Vorträge</h1>', $navPage);
        self::assertStringContainsString('nav-primary', $navPage);
        self::assertStringContainsString('href="/de/vortraege/"', $navPage);

        self::assertFileDoesNotExist($result->releaseDir . '/de/entwurf/index.html', 'a draft must never reach a release');
        self::assertFileDoesNotExist($result->releaseDir . '/de/zukunftsbeitrag/index.html', 'a not-yet-due scheduled post must never reach a release');
        self::assertFileDoesNotExist($result->releaseDir . '/de/impressum/index.html', '*.example.md is never content');
    }

    public function testDryRunRunsEveryStageButWritesNothingToDisk(): void
    {
        $pipeline = new BuildPipeline($this->config(self::REAL_TEMPLATES), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions(dryRun: true));

        self::assertNull($result->releaseDir);
        self::assertSame(5, $result->documentCount);
        self::assertSame([], glob($this->scratchDir . '/releases/*') ?: []);
    }

    public function testATemplateErrorAbortsTheBuildWithNoOutputWritten(): void
    {
        $brokenTemplatesDir = $this->scratchDir . '/empty-templates';
        mkdir($brokenTemplatesDir, 0o755, true);

        $pipeline = new BuildPipeline($this->config($brokenTemplatesDir), self::LANG_DIR);

        try {
            $pipeline->run(new BuildOptions());
            self::fail('expected a template rendering failure');
        } catch (\Throwable) {
            // Any failure is acceptable here — what matters is nothing was written.
        }

        self::assertSame([], glob($this->scratchDir . '/releases/*') ?: []);
    }

    public function testASecondConcurrentBuildIsRejectedNotQueued(): void
    {
        $config = $this->config(self::REAL_TEMPLATES);
        $lock   = new BuildLock(rtrim($config->paths->var, '/') . '/build.lock');
        $lock->acquire();

        try {
            $pipeline = new BuildPipeline($config, self::LANG_DIR);
            $this->expectException(BuildException::class);
            $this->expectExceptionMessageMatches('/already running/');
            $pipeline->run(new BuildOptions());
        } finally {
            $lock->release();
        }
    }

    private function read(string $releaseDir, string $route): string
    {
        $path = rtrim($releaseDir, '/') . rtrim($route, '/') . '/index.html';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function config(string $templatesDir): Config
    {
        return new Config(
            baseUrl: 'https://blog.silverday.de',
            title: 'SilverDay',
            timezone: 'Europe/Berlin',
            languages: ['de', 'en'],
            defaultLanguage: 'en',
            urlPrefix: UrlPrefix::Always,
            permalink: '/{slug}/',
            postsPerPage: 10,
            feedItems: 20,
            paths: new ConfigPaths(
                content: self::FIXTURE_CONTENT,
                templates: $templatesDir,
                releases: $this->scratchDir . '/releases',
                public: $this->scratchDir . '/public',
                var: $this->scratchDir . '/var',
            ),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, 750 * 1024),
            mail: new MailSettings(false, 'a@example.com', 'a@example.com', 'a@example.com'),
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

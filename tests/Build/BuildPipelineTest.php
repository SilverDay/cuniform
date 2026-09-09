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
        self::assertStringContainsString(
            'hreflang="en" href="https://blog.silverday.de/en/security-culture/"',
            $dePost,
            'hreflang hrefs must be absolute'
        );
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

        // The stylesheet link the page actually uses must be the exact
        // fingerprinted file the Emit stage wrote (SPEC §11.4).
        $matched = preg_match('/href="(\/style\.[0-9a-f]{8}\.css)"/', $dePost, $cssMatch);
        self::assertSame(1, $matched, 'page must link a fingerprinted stylesheet');
        $cssPath = $cssMatch[1] ?? throw new \RuntimeException('unreachable');
        self::assertFileExists($result->releaseDir . $cssPath);
        self::assertStringEqualsFile($result->releaseDir . $cssPath, (string) file_get_contents(self::REAL_TEMPLATES . '/style.css'));
    }

    public function testEmitStageWritesFeedsSitemapSearchIndexRobotsAndSecurityTxt(): void
    {
        $pipeline = new BuildPipeline($this->config(self::REAL_TEMPLATES), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions());
        self::assertNotNull($result->releaseDir);

        $deFeed = (string) file_get_contents($result->releaseDir . '/de/feed.xml');
        self::assertStringContainsString('<language>de</language>', $deFeed);
        self::assertStringContainsString('<title>Sicherheitskultur</title>', $deFeed);
        self::assertStringContainsString('https://blog.silverday.de/de/sicherheitskultur/', $deFeed);
        self::assertStringContainsString('Gebaut mit Cuniform.', $deFeed, 'feed items carry full content');
        self::assertStringContainsString('src="https://blog.silverday.de/media/2026/03/photo.jpg"', $deFeed, 'relative URLs must be absolute in feeds');

        $deAtom = (string) file_get_contents($result->releaseDir . '/de/atom.xml');
        self::assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $deAtom);
        self::assertStringContainsString('https://blog.silverday.de/de/sicherheitskultur/', $deAtom);

        $sitemap = (string) file_get_contents($result->releaseDir . '/sitemap.xml');
        self::assertStringContainsString('<loc>https://blog.silverday.de/de/sicherheitskultur/</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://blog.silverday.de/en/security-culture/</loc>', $sitemap);
        self::assertStringContainsString('hreflang="en" href="https://blog.silverday.de/en/security-culture/"', $sitemap);

        $searchIndex = json_decode((string) file_get_contents($result->releaseDir . '/search-index.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(5, $searchIndex);
        $dePostEntry = null;
        foreach ($searchIndex as $entry) {
            if ($entry['path'] === '/de/sicherheitskultur/') {
                $dePostEntry = $entry;
            }
        }
        self::assertNotNull($dePostEntry);
        self::assertSame('de', $dePostEntry['lang']);
        self::assertSame('post', $dePostEntry['type']);
        self::assertSame(['awareness'], $dePostEntry['tags']);
        self::assertStringContainsString('Kultur schlägt Compliance.', $dePostEntry['body_plain']);
        self::assertStringNotContainsString('<', $dePostEntry['body_plain'], 'body_plain must be plain text');

        $robots = (string) file_get_contents($result->releaseDir . '/robots.txt');
        self::assertStringContainsString('Disallow: /admin', $robots);
        self::assertStringContainsString('Sitemap: https://blog.silverday.de/sitemap.xml', $robots);

        $securityTxt = (string) file_get_contents($result->releaseDir . '/.well-known/security.txt');
        self::assertStringContainsString('Contact: mailto:', $securityTxt);
        self::assertStringContainsString('Expires:', $securityTxt);
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
        // A real style.css (so the Emit stage's asset fingerprinting succeeds
        // and doesn't mask the failure this test actually wants to exercise),
        // but no page.php/post.php/layout.php — the first template render
        // must fail with nothing written.
        $brokenTemplatesDir = $this->scratchDir . '/broken-templates';
        mkdir($brokenTemplatesDir, 0o755, true);
        copy(self::REAL_TEMPLATES . '/style.css', $brokenTemplatesDir . '/style.css');

        $pipeline = new BuildPipeline($this->config($brokenTemplatesDir), self::LANG_DIR);

        try {
            $pipeline->run(new BuildOptions());
            self::fail('expected a template rendering failure');
        } catch (\Cuniform\Render\RenderException $e) {
            self::assertStringContainsString('Template not found', $e->getMessage());
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

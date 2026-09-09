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

        // The media file the front-matter `image` and [figure] shortcode both
        // reference must actually exist in the release (SPEC §7.10, §10.3).
        self::assertFileExists($result->releaseDir . '/media/2026/03/photo.jpg');
    }

    public function testGeneratedListingRoutesRenderForTheFixtureCorpus(): void
    {
        $pipeline = new BuildPipeline($this->config(self::REAL_TEMPLATES), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions());
        self::assertNotNull($result->releaseDir);

        $deHome = $this->read($result->releaseDir, '/de/');
        self::assertStringContainsString('Sicherheitskultur', $deHome, 'the home index must list the language\'s own posts');

        $enHome = $this->read($result->releaseDir, '/en/');
        self::assertStringContainsString('Security Culture', $enHome);

        $deTag = $this->read($result->releaseDir, '/de/tag/awareness/');
        self::assertStringContainsString('Sicherheitskultur', $deTag);
        self::assertStringNotContainsString('Security Culture', $deTag, 'tags are language-scoped (SPEC §7.10)');

        $enArchive = $this->read($result->releaseDir, '/en/archive/2026/');
        self::assertStringContainsString('Security Culture', $enArchive);

        $enSearch = $this->read($result->releaseDir, '/en/search/');
        self::assertStringContainsString('data-lang="en"', $enSearch);
        $matched = preg_match('/src="(\/search\.[0-9a-f]{8}\.js)"/', $enSearch, $jsMatch);
        self::assertSame(1, $matched, 'search page must link the fingerprinted search script');
        self::assertFileExists($result->releaseDir . $jsMatch[1]);

        self::assertFileExists($result->releaseDir . '/de/404.html');
        $de404 = (string) file_get_contents($result->releaseDir . '/de/404.html');
        self::assertStringContainsString('nav-primary', $de404, 'per-language 404 uses the normal layout/nav chain');

        $neutral404 = (string) file_get_contents($result->releaseDir . '/404.html');
        self::assertStringContainsString('lang="de"', $neutral404);
        self::assertStringContainsString('lang="en"', $neutral404);

        $sitemap = (string) file_get_contents($result->releaseDir . '/sitemap.xml');
        self::assertStringContainsString('<loc>https://blog.silverday.de/de/</loc>', $sitemap);
        self::assertStringContainsString('<loc>https://blog.silverday.de/de/tag/awareness/</loc>', $sitemap);
    }

    public function testASecondUnchangedBuildReusesEveryDocumentFromCache(): void
    {
        $config   = $this->config(self::REAL_TEMPLATES, $this->mutableContentDir());
        $pipeline = new BuildPipeline($config, self::LANG_DIR);

        $first = $pipeline->run(new BuildOptions());
        self::assertSame(0, $first->reusedDocumentCount, 'nothing to reuse on the very first build');

        $second = $pipeline->run(new BuildOptions());
        self::assertSame($second->documentCount, $second->reusedDocumentCount, 'nothing changed — every document should be reused');
    }

    public function testEditingOnePostIsReflectedAndOnlyPartiallyInvalidatesTheCache(): void
    {
        $contentDir = $this->mutableContentDir();
        $config     = $this->config(self::REAL_TEMPLATES, $contentDir);
        $pipeline   = new BuildPipeline($config, self::LANG_DIR);

        $pipeline->run(new BuildOptions());

        $postPath = $contentDir . '/posts/de/2026/2026-03-14-sicherheitskultur.md';
        $edited   = str_replace('Kultur schlägt Compliance.', 'Kultur schlägt Compliance, immer noch.', (string) file_get_contents($postPath));
        self::assertNotSame((string) file_get_contents($postPath), $edited, 'sanity: the replacement must actually change the file');
        file_put_contents($postPath, $edited);

        $second = $pipeline->run(new BuildOptions());
        self::assertNotNull($second->releaseDir);

        self::assertGreaterThan(0, $second->reusedDocumentCount, 'unrelated documents must still be reused');
        self::assertLessThan($second->documentCount, $second->reusedDocumentCount, 'the edited document itself must not be reused');

        $dePost = $this->read($second->releaseDir, '/de/sicherheitskultur/');
        self::assertStringContainsString('Kultur schlägt Compliance, immer noch.', $dePost);
    }

    public function testFullFlagForcesEveryDocumentToBeReRendered(): void
    {
        $config   = $this->config(self::REAL_TEMPLATES, $this->mutableContentDir());
        $pipeline = new BuildPipeline($config, self::LANG_DIR);

        $pipeline->run(new BuildOptions());
        $second = $pipeline->run(new BuildOptions(full: true));

        self::assertSame(0, $second->reusedDocumentCount);
    }

    public function testEditingATemplateInvalidatesEveryDocumentOnTheNextBuild(): void
    {
        $templatesDir = $this->scratchDir . '/templates-copy';
        $this->copyDirectory(self::REAL_TEMPLATES, $templatesDir);

        $config   = $this->config($templatesDir, $this->mutableContentDir());
        $pipeline = new BuildPipeline($config, self::LANG_DIR);

        $pipeline->run(new BuildOptions());

        $postTemplate = $templatesDir . '/post.php';
        file_put_contents($postTemplate, str_replace('post-body', 'post-body post-body-v2', (string) file_get_contents($postTemplate)));

        $second = $pipeline->run(new BuildOptions());

        self::assertSame(0, $second->reusedDocumentCount, 'a changed template must invalidate every cached document');
    }

    public function testEditingAUiStringFileInvalidatesEveryDocumentOnTheNextBuild(): void
    {
        $langDir = $this->scratchDir . '/lang-copy';
        $this->copyDirectory(self::LANG_DIR, $langDir);

        $config   = $this->config(self::REAL_TEMPLATES, $this->mutableContentDir());
        $pipeline = new BuildPipeline($config, $langDir);

        $pipeline->run(new BuildOptions());

        $enStrings = $langDir . '/en.php';
        file_put_contents($enStrings, str_replace("'Updated on'", "'Updated on!'", (string) file_get_contents($enStrings)));

        $second = $pipeline->run(new BuildOptions());

        self::assertSame(0, $second->reusedDocumentCount, 'a changed UI string file must invalidate every cached document');
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

    public function testRedirectMapCombinesAliasesAndManualEntriesAndDropsTheRootEntry(): void
    {
        $pipeline = new BuildPipeline($this->config(self::REAL_TEMPLATES), self::LANG_DIR);
        $result   = $pipeline->run(new BuildOptions());
        self::assertNotNull($result->releaseDir);

        $redirects = (string) file_get_contents($result->releaseDir . '/redirects.conf');

        // The hand-written manual entry (content/redirects.map fixture).
        self::assertStringContainsString(
            'RedirectMatch 301 ^/feed\.xml$ https://blog.silverday.de/de/feed.xml',
            $redirects
        );

        // The alias-derived entry from the German post's front matter.
        self::assertStringContainsString(
            'RedirectMatch 301 ^/de/alter\-pfad/$ https://blog.silverday.de/de/sicherheitskultur/',
            $redirects
        );

        // The fixture's "/" entry must never reach the compiled output.
        self::assertStringNotContainsString('^/$', $redirects);
        self::assertNotEmpty(array_filter(
            $result->warnings,
            static fn (string $w): bool => str_contains($w, "redirects.map entry for '/'")
        ));
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
        // Real style.css/search.js (so the Emit stage's asset fingerprinting
        // succeeds and doesn't mask the failure this test actually wants to
        // exercise), but no page.php/post.php/layout.php — the first
        // template render must fail with nothing written.
        $brokenTemplatesDir = $this->scratchDir . '/broken-templates';
        mkdir($brokenTemplatesDir, 0o755, true);
        copy(self::REAL_TEMPLATES . '/style.css', $brokenTemplatesDir . '/style.css');
        copy(self::REAL_TEMPLATES . '/search.js', $brokenTemplatesDir . '/search.js');

        $pipeline = new BuildPipeline($this->config($brokenTemplatesDir), self::LANG_DIR);

        try {
            $pipeline->run(new BuildOptions());
            self::fail('expected a template rendering failure');
        } catch (\Cuniform\Render\RenderException $e) {
            self::assertStringContainsString('Template not found', $e->getMessage());
        }

        self::assertSame([], glob($this->scratchDir . '/releases/*') ?: []);
    }

    public function testUrlSchemeChangeIsBlockedThenAllowedWithTheFlag(): void
    {
        $config = $this->config(self::REAL_TEMPLATES);
        (new BuildPipeline($config, self::LANG_DIR))->run(new BuildOptions());

        $changed = new Config(
            baseUrl: $config->baseUrl,
            title: $config->title,
            timezone: $config->timezone,
            languages: $config->languages,
            defaultLanguage: 'de',
            urlPrefix: $config->urlPrefix,
            permalink: $config->permalink,
            postsPerPage: $config->postsPerPage,
            feedItems: $config->feedItems,
            paths: $config->paths,
            build: $config->build,
            mail: $config->mail,
        );

        try {
            (new BuildPipeline($changed, self::LANG_DIR))->run(new BuildOptions());
            self::fail('expected a URL-scheme-change failure');
        } catch (BuildException $e) {
            self::assertStringContainsString('--allow-url-scheme-change', $e->getMessage());
        }

        $result = (new BuildPipeline($changed, self::LANG_DIR))->run(new BuildOptions(allowUrlSchemeChange: true));
        self::assertNotNull($result->releaseDir);
    }

    public function testDryRunStillRunsVerificationAndCanFail(): void
    {
        $brokenContent = $this->scratchDir . '/broken-content';
        mkdir("{$brokenContent}/posts/de/2026", 0o755, true);
        file_put_contents(
            "{$brokenContent}/posts/de/2026/2026-01-01-x.md",
            <<<'MD'
            ---
            title: "X"
            slug: "x"
            status: "published"
            summary: "Summary."
            date: "2026-01-01"
            ---
            See [broken](/de/does-not-exist/).
            MD
        );

        $config  = $this->config(self::REAL_TEMPLATES);
        $broken  = new Config(
            baseUrl: $config->baseUrl,
            title: $config->title,
            timezone: $config->timezone,
            languages: ['de'],
            defaultLanguage: 'de',
            urlPrefix: $config->urlPrefix,
            permalink: $config->permalink,
            postsPerPage: $config->postsPerPage,
            feedItems: $config->feedItems,
            paths: new ConfigPaths(
                content: $brokenContent,
                templates: self::REAL_TEMPLATES,
                releases: $config->paths->releases,
                public: $config->paths->public,
                var: $config->paths->var,
            ),
            build: $config->build,
            mail: $config->mail,
        );

        try {
            (new BuildPipeline($broken, self::LANG_DIR))->run(new BuildOptions(dryRun: true));
            self::fail('expected a broken-link failure even on a dry run');
        } catch (BuildException $e) {
            self::assertStringContainsString('does-not-exist', $e->getMessage());
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

    private function config(string $templatesDir, ?string $contentDir = null): Config
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
                content: $contentDir ?? self::FIXTURE_CONTENT,
                templates: $templatesDir,
                releases: $this->scratchDir . '/releases',
                public: $this->scratchDir . '/public',
                var: $this->scratchDir . '/var',
            ),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, 750 * 1024),
            mail: new MailSettings(false, 'a@example.com', 'a@example.com', 'a@example.com'),
        );
    }

    /**
     * A writable copy of the fixture content tree, for tests that need to
     * edit a file between two builds — self::FIXTURE_CONTENT is shared and
     * must stay read-only.
     */
    private function mutableContentDir(): string
    {
        $target = $this->scratchDir . '/content';
        $this->copyDirectory(self::FIXTURE_CONTENT, $target);

        return $target;
    }

    private function copyDirectory(string $source, string $target): void
    {
        mkdir($target, 0o755, true);
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . '/' . $item;
            $to   = $target . '/' . $item;
            is_dir($from) ? $this->copyDirectory($from, $to) : copy($from, $to);
        }
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
            is_link($full) || !is_dir($full) ? unlink($full) : $this->removeDirectory($full);
        }

        rmdir($path);
    }
}

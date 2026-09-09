<?php

declare(strict_types=1);

namespace Cuniform\Tests\Build;

use Cuniform\Build\BuildException;
use Cuniform\Build\DiscoveredDocument;
use Cuniform\Build\ParsedDocument;
use Cuniform\Build\SiteResolver;
use Cuniform\Config\BuildSettings;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigPaths;
use Cuniform\Config\MailSettings;
use Cuniform\Config\UrlPrefix;
use Cuniform\Content\FrontMatter\DocumentKind;
use Cuniform\Content\FrontMatter\DocumentStatus;
use Cuniform\Content\FrontMatter\NavGroup;
use Cuniform\Content\FrontMatter\PageFrontMatter;
use Cuniform\Content\FrontMatter\PostFrontMatter;
use Cuniform\Content\FrontMatter\SharedFrontMatter;
use PHPUnit\Framework\TestCase;

final class SiteResolverTest extends TestCase
{
    private string $contentRoot;

    protected function setUp(): void
    {
        $this->contentRoot = sys_get_temp_dir() . '/cuniform_resolve_' . uniqid();
        mkdir($this->contentRoot . '/media/2026/03', 0o755, true);
        file_put_contents($this->contentRoot . '/media/2026/03/photo.jpg', 'not-really-a-jpeg');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->contentRoot);
    }

    public function testPublishedPostAndPageGetRoutesAndAreIncluded(): void
    {
        $post = $this->post('de', 'sicherheitskultur', DocumentStatus::Published);
        $page = $this->page('de', 'impressum', 'pages/de/impressum.md', DocumentStatus::Published);

        $site = $this->resolver(['de'])->resolve([$post, $page], $this->now());

        $urls = array_map(static fn ($d) => $d->url, $site->documents);
        self::assertContains('/de/sicherheitskultur/', $urls);
        self::assertContains('/de/impressum/', $urls);
    }

    public function testDraftIsExcluded(): void
    {
        $draft = $this->post('de', 'draft-post', DocumentStatus::Draft);

        $site = $this->resolver(['de'])->resolve([$draft], $this->now());

        self::assertSame([], $site->documents);
    }

    public function testScheduledPostBeforeItsDateIsExcluded(): void
    {
        $future = $this->post('de', 'future-post', DocumentStatus::Scheduled, date: '2099-01-01');

        $site = $this->resolver(['de'])->resolve([$future], $this->now());

        self::assertSame([], $site->documents);
    }

    public function testScheduledPostAtOrAfterItsDateIsIncluded(): void
    {
        $due = $this->post('de', 'due-post', DocumentStatus::Scheduled, date: '2020-01-01');

        $site = $this->resolver(['de'])->resolve([$due], $this->now());

        self::assertCount(1, $site->documents);
    }

    public function testRouteCollisionIsABuildError(): void
    {
        $a = $this->post('de', 'same-slug', DocumentStatus::Published);
        $b = $this->page('de', 'same-slug', 'pages/de/same-slug.md', DocumentStatus::Published);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/collision/');
        $this->resolver(['de'])->resolve([$a, $b], $this->now());
    }

    public function testReservedSlugIsABuildError(): void
    {
        $page = $this->page('de', 'tag', 'pages/de/tag.md', DocumentStatus::Published);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/reserved slug/');
        $this->resolver(['de'])->resolve([$page], $this->now());
    }

    public function testAliasCollidingWithARealRouteIsABuildError(): void
    {
        $target = $this->post('de', 'sicherheitskultur', DocumentStatus::Published);
        $withAlias = $this->post('de', 'other-post', DocumentStatus::Published, aliases: ['/de/sicherheitskultur/']);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/alias/');
        $this->resolver(['de'])->resolve([$target, $withAlias], $this->now());
    }

    public function testImageThatDoesNotResolveIsABuildError(): void
    {
        $post = $this->post('de', 'with-broken-image', DocumentStatus::Published, image: '/media/does-not-exist.jpg');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/does not resolve/');
        $this->resolver(['de'])->resolve([$post], $this->now());
    }

    public function testImageThatResolvesIsAccepted(): void
    {
        $post = $this->post('de', 'with-image', DocumentStatus::Published, image: '/media/2026/03/photo.jpg');

        $site = $this->resolver(['de'])->resolve([$post], $this->now());

        self::assertCount(1, $site->documents);
    }

    public function testDuplicateTranslationKeyWithinOneLanguageIsAnError(): void
    {
        $a = $this->post('de', 'post-a', DocumentStatus::Published, translationKey: 'shared-key');
        $b = $this->post('de', 'post-b', DocumentStatus::Published, translationKey: 'shared-key');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessageMatches('/appears twice within language/');
        $this->resolver(['de'])->resolve([$a, $b], $this->now());
    }

    public function testTranslatedDocumentsGetASymmetricSelfReferencingHreflangSet(): void
    {
        $de = $this->post('de', 'sicherheitskultur', DocumentStatus::Published, translationKey: 'k');
        $en = $this->post('en', 'security-culture', DocumentStatus::Published, translationKey: 'k');

        $site = $this->resolver(['de', 'en'])->resolve([$de, $en], $this->now());

        $deDoc = $this->findByUrl($site, '/de/sicherheitskultur/');
        $enDoc = $this->findByUrl($site, '/en/security-culture/');

        self::assertNotNull($deDoc->hreflang);
        self::assertNotNull($enDoc->hreflang);

        $deTargets = array_map(static fn ($a) => $a->hreflang, $deDoc->hreflang->alternates);
        self::assertContains('de', $deTargets);
        self::assertContains('en', $deTargets);
        self::assertContains('x-default', $deTargets);
    }

    public function testSingleLanguageSiteHasNoHreflang(): void
    {
        $post = $this->post('de', 'sicherheitskultur', DocumentStatus::Published);

        $site = $this->resolver(['de'])->resolve([$post], $this->now());

        self::assertNull($site->documents[0]->hreflang);
    }

    public function testNavTreeIncludesAPageWithNavOrderSet(): void
    {
        $page = $this->page(
            'de',
            'vortraege',
            'pages/de/vortraege/index.md',
            DocumentStatus::Published,
            navOrder: 1,
            navGroup: NavGroup::Primary,
        );

        $site = $this->resolver(['de'])->resolve([$page], $this->now());

        self::assertCount(1, $site->navByLanguage['de']['primary']);
        self::assertSame('/de/vortraege/', $site->navByLanguage['de']['primary'][0]->url);
    }

    public function testPageWithoutNavOrderIsReachableButNotInNav(): void
    {
        $page = $this->page('de', 'impressum', 'pages/de/impressum.md', DocumentStatus::Published);

        $site = $this->resolver(['de'])->resolve([$page], $this->now());

        self::assertSame([], $site->navByLanguage['de']['primary']);
        self::assertCount(1, $site->documents);
    }

    private function findByUrl(\Cuniform\Build\ResolvedSite $site, string $url): \Cuniform\Build\ResolvedDocument
    {
        foreach ($site->documents as $document) {
            if ($document->url === $url) {
                return $document;
            }
        }

        self::fail("no resolved document with url {$url}");
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-06-01T00:00:00Z');
    }

    /**
     * @param list<string> $languages
     */
    private function resolver(array $languages): SiteResolver
    {
        return new SiteResolver($this->config($languages));
    }

    /**
     * @param list<string> $languages
     */
    private function config(array $languages): Config
    {
        return new Config(
            baseUrl: 'https://blog.silverday.de',
            title: 'SilverDay',
            timezone: 'Europe/Berlin',
            languages: $languages,
            defaultLanguage: $languages[0],
            urlPrefix: UrlPrefix::Always,
            permalink: '/{slug}/',
            postsPerPage: 10,
            feedItems: 20,
            paths: new ConfigPaths(
                content: $this->contentRoot,
                templates: '/tmp/unused-templates',
                releases: '/tmp/unused-releases',
                public: '/tmp/unused-public',
                var: '/tmp/unused-var',
            ),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, 750 * 1024),
            mail: new MailSettings(false, 'a@example.com', 'a@example.com', 'a@example.com'),
        );
    }

    /**
     * @param list<string> $aliases
     */
    private function post(
        string $language,
        string $slug,
        DocumentStatus $status,
        string $date = '2026-03-14',
        ?string $translationKey = null,
        ?string $image = null,
        array $aliases = [],
        string $identifierSuffix = '',
    ): ParsedDocument {
        $shared = new SharedFrontMatter(
            title: ucfirst($slug),
            slug: $slug,
            status: $status,
            summary: 'Summary.',
            translationKey: $translationKey,
            updated: null,
            image: $image,
            imageAlt: $image !== null ? 'alt' : null,
            canonical: null,
            noindex: false,
            aliases: $aliases,
            toc: false,
            sourceId: null,
        );

        $frontMatter = new PostFrontMatter($shared, new \DateTimeImmutable($date), [], null, '<p>Body.</p>');

        $relativePath = "posts/{$language}/2026/2026-03-14-{$slug}{$identifierSuffix}.md";
        $discovered   = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Post, $language, 0, 'x');

        return new ParsedDocument($discovered, $frontMatter);
    }

    private function page(
        string $language,
        string $slug,
        string $relativePath,
        DocumentStatus $status,
        ?int $navOrder = null,
        ?NavGroup $navGroup = null,
    ): ParsedDocument {
        $shared = new SharedFrontMatter(
            title: ucfirst($slug),
            slug: $slug,
            status: $status,
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

        $frontMatter = new PageFrontMatter(
            $shared,
            'page.php',
            null,
            $navOrder,
            null,
            $navGroup,
            null,
            null,
            '<p>Body.</p>',
        );

        $discovered = new DiscoveredDocument("/tmp/{$relativePath}", $relativePath, DocumentKind::Page, $language, 0, 'x');

        return new ParsedDocument($discovered, $frontMatter);
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

<?php

declare(strict_types=1);

namespace Cuniform\Tests\Admin\Reporting;

use Cuniform\Admin\Reporting\AdminSiteReport;
use Cuniform\Config\BuildSettings;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigPaths;
use Cuniform\Config\MailSettings;
use Cuniform\Config\UrlPrefix;
use PHPUnit\Framework\TestCase;

/**
 * Exercises AdminSiteReport against the same real fixture corpus
 * BuildPipelineTest/PreviewRendererTest use — the point of T30/T33's reuse
 * design is that this reads exactly what a real build would resolve, so
 * asserting against the fixture's own known shape is the meaningful check.
 */
final class AdminSiteReportTest extends TestCase
{
    private const FIXTURE_CONTENT = __DIR__ . '/../../fixtures/Build/content';
    private const REAL_TEMPLATES  = __DIR__ . '/../../../templates';
    private const LANG_DIR        = __DIR__ . '/../../../config/lang';

    public function testNavTreeIncludesAPublishedPageWithNavOrderSet(): void
    {
        $result = (new AdminSiteReport($this->config(), self::LANG_DIR))->build();

        $primary = $result->navByLanguage['de']['primary'];
        self::assertNotEmpty($primary);
        self::assertSame('Vorträge', $primary[0]->label);
        self::assertStringContainsString('/de/vortraege/', $primary[0]->url);
    }

    public function testTagArchivesAreScopedPerLanguage(): void
    {
        $result = (new AdminSiteReport($this->config(), self::LANG_DIR))->build();

        $deTags = $result->listing->tagsByLanguage['de'];
        $enTags = $result->listing->tagsByLanguage['en'];

        self::assertCount(1, $deTags);
        self::assertSame('awareness', $deTags[0]->slug);
        self::assertCount(1, $deTags[0]->posts);

        self::assertCount(1, $enTags);
        self::assertSame('awareness', $enTags[0]->slug);
        self::assertCount(1, $enTags[0]->posts);
    }

    public function testRedirectsIncludeBothAliasDerivedAndManualEntries(): void
    {
        $result = (new AdminSiteReport($this->config(), self::LANG_DIR))->build();

        $bySource = [];
        foreach ($result->redirects as $entry) {
            $bySource[$entry->oldPath] = $entry;
        }

        self::assertArrayHasKey('/de/alter-pfad/', $bySource, 'alias from front matter');
        self::assertStringContainsString('/de/sicherheitskultur/', $bySource['/de/alter-pfad/']->newPath);

        self::assertArrayHasKey('/feed.xml', $bySource, 'manual entry from redirects.map');
        self::assertSame('/de/feed.xml', $bySource['/feed.xml']->newPath);
    }

    public function testARootRedirectMapEntryIsDroppedWithAWarningNotIncluded(): void
    {
        $result = (new AdminSiteReport($this->config(), self::LANG_DIR))->build();

        foreach ($result->redirects as $entry) {
            self::assertNotSame('/', $entry->oldPath);
        }

        $hasRootWarning = false;
        foreach ($result->warnings as $warning) {
            if (str_contains($warning, "entry for '/'")) {
                $hasRootWarning = true;
            }
        }
        self::assertTrue($hasRootWarning);
    }

    private function config(): Config
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
                templates: self::REAL_TEMPLATES,
                releases: sys_get_temp_dir(),
                public: sys_get_temp_dir(),
                var: sys_get_temp_dir(),
            ),
            build: new BuildSettings(5, 2 * 1024 * 1024, 0.10, 750 * 1024),
            mail: new MailSettings(false, 'a@example.com', 'a@example.com', 'a@example.com'),
        );
    }
}

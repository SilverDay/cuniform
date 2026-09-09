<?php

declare(strict_types=1);

namespace Cuniform\Tests\Cli;

use Cuniform\Cli\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/cuniform_app_' . uniqid();
        mkdir($this->projectRoot . '/config/lang', 0o755, true);
        mkdir($this->projectRoot . '/content/posts', 0o755, true);
        mkdir($this->projectRoot . '/content/pages', 0o755, true);
        mkdir($this->projectRoot . '/var', 0o755, true);
        mkdir($this->projectRoot . '/releases', 0o755, true);

        // The real file, not an empty stub: every configured language needs
        // every key ListingTemplateStage's templates call t() for (T17),
        // and this fixture is only ever built with a single 'en' language,
        // so there's no cross-language mismatch to keep in sync by hand.
        copy(__DIR__ . '/../../config/lang/en.php', $this->projectRoot . '/config/lang/en.php');
        file_put_contents($this->projectRoot . '/config/site.php', $this->configFileContents());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testNoArgumentsFailsWithUsage(): void
    {
        self::assertSame(2, $this->app()->run([]));
    }

    public function testUnknownCommandFails(): void
    {
        self::assertSame(2, $this->app()->run(['publish']));
    }

    public function testUnknownFlagFails(): void
    {
        self::assertSame(2, $this->app()->run(['build', '--bogus']));
    }

    public function testRollbackFailsWhenNothingHasBeenDeployedYet(): void
    {
        self::assertSame(1, $this->app()->run(['build', '--rollback']));
    }

    public function testDryRunBuildSucceedsOnAnEmptyCorpus(): void
    {
        self::assertSame(0, $this->app()->run(['build', '--dry-run']));
        self::assertSame([], glob($this->projectRoot . '/releases/*') ?: []);
    }

    public function testPlainBuildSucceedsWritesAReleaseAndDeploysIt(): void
    {
        self::assertSame(0, $this->app()->run(['build']));

        $releases = glob($this->projectRoot . '/releases/*') ?: [];
        self::assertNotSame([], $releases);
        self::assertTrue(is_link($this->projectRoot . '/public'));
        $target = readlink($this->projectRoot . '/public');
        self::assertIsString($target);
        self::assertSame(realpath($releases[0]), realpath($target));
    }

    public function testRollbackRepointsPublicAtThePreviousRelease(): void
    {
        self::assertSame(0, $this->app()->run(['build']));
        $first = readlink($this->projectRoot . '/public');

        // Guarantee a distinct release timestamp from the first build.
        sleep(1);
        self::assertSame(0, $this->app()->run(['build']));
        $second = readlink($this->projectRoot . '/public');
        self::assertNotSame($first, $second);

        self::assertSame(0, $this->app()->run(['build', '--rollback']));
        self::assertSame($first, readlink($this->projectRoot . '/public'));
    }

    public function testFullFlagIsAccepted(): void
    {
        self::assertSame(0, $this->app()->run(['build', '--full']));
    }

    public function testAllowUrlSchemeChangeFlagIsAccepted(): void
    {
        self::assertSame(0, $this->app()->run(['build', '--dry-run', '--allow-url-scheme-change']));
    }

    public function testBuildFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['build', '--dry-run']));
    }

    public function testLegacyUrlsWithNoBaseUrlFailsWithUsage(): void
    {
        self::assertSame(2, $this->app()->run(['legacy-urls']));
    }

    public function testLegacyUrlsWithAnUnknownOptionFails(): void
    {
        self::assertSame(2, $this->app()->run(['legacy-urls', 'https://legacy.test', '--bogus']));
    }

    public function testLegacyUrlsFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        // Argument parsing happens before config is loaded, and config
        // loading happens before any network access — this never reaches
        // the crawler, so it's safe without a real HTTP call (php-style.md:
        // "No network ... in tests"). The crawler's own behaviour is
        // covered directly by LegacyUrlCrawlerTest.
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['legacy-urls', 'https://legacy.test']));
    }

    private function app(): Application
    {
        return new Application($this->projectRoot);
    }

    private function configFileContents(): string
    {
        $root      = addslashes($this->projectRoot);
        $templates = addslashes(__DIR__ . '/../../templates');

        return <<<PHP
        <?php
        declare(strict_types=1);
        return [
            'base_url' => 'https://example.test',
            'title' => 'Fixture',
            'timezone' => 'UTC',
            'languages' => ['en'],
            'default_language' => 'en',
            'url_prefix' => 'always',
            'permalink' => '/{slug}/',
            'posts_per_page' => 10,
            'feed_items' => 20,
            'paths' => [
                'content' => '{$root}/content',
                'templates' => '{$templates}',
                'releases' => '{$root}/releases',
                'public' => '{$root}/public',
                'var' => '{$root}/var',
            ],
            'build' => [
                'retain_releases' => 5,
                'max_document_bytes' => 2097152,
                'page_count_drop_threshold' => 0.10,
                'search_index_warn_bytes' => 768000,
            ],
            'mail' => [
                'enabled' => false,
                'from' => 'a@example.com',
                'notify' => 'a@example.com',
                'envelope_sender' => 'a@example.com',
            ],
        ];
        PHP;
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

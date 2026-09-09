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

        file_put_contents($this->projectRoot . '/config/lang/en.php', "<?php\nreturn [];\n");
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

    public function testRollbackIsNotYetImplemented(): void
    {
        self::assertSame(1, $this->app()->run(['build', '--rollback']));
    }

    public function testDryRunBuildSucceedsOnAnEmptyCorpus(): void
    {
        self::assertSame(0, $this->app()->run(['build', '--dry-run']));
        self::assertSame([], glob($this->projectRoot . '/releases/*') ?: []);
    }

    public function testPlainBuildSucceedsAndWritesARelease(): void
    {
        self::assertSame(0, $this->app()->run(['build']));
        self::assertNotSame([], glob($this->projectRoot . '/releases/*') ?: []);
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
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

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

    public function testSetupPublicReportsNothingToDoWhenAlreadyASymlink(): void
    {
        mkdir($this->projectRoot . '/releases/20260101000000', 0o755, true);
        symlink($this->projectRoot . '/releases/20260101000000', $this->projectRoot . '/public');

        self::assertSame(0, $this->app()->run(['setup-public']));
    }

    public function testSetupPublicMovesANonEmptyRealDirectoryAside(): void
    {
        mkdir($this->projectRoot . '/public', 0o755, true);
        file_put_contents($this->projectRoot . '/public/index.html', 'placeholder');

        self::assertSame(0, $this->app()->run(['setup-public']));

        self::assertFalse(is_dir($this->projectRoot . '/public'));
        self::assertNotSame([], glob($this->projectRoot . '/public.provisioned-*') ?: []);
    }

    public function testSetupPublicFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['setup-public']));
    }

    public function testImportWxrWithNoPathFailsWithUsage(): void
    {
        self::assertSame(2, $this->app()->run(['import-wxr']));
    }

    public function testImportWxrWithAnUnknownOptionFails(): void
    {
        self::assertSame(2, $this->app()->run(['import-wxr', '/tmp/x.xml', '--bogus']));
    }

    public function testImportWxrFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['import-wxr', '/tmp/x.xml']));
    }

    public function testImportWxrFailureFromAMissingExportFileIsReportedNotFatal(): void
    {
        self::assertSame(1, $this->app()->run(['import-wxr', $this->projectRoot . '/does-not-exist.xml']));
    }

    public function testImportWxrWritesDocumentsToTheDefaultStagingDirectory(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/sample.xml';

        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath]));

        $written = $this->projectRoot . '/var/import/posts/en/2020/2020-01-15-a-published-post.md';
        self::assertFileExists($written);
        self::assertStringContainsString('title: "A Published Post"', (string) file_get_contents($written));
    }

    public function testImportWxrWritesToACustomOutputDirWhenGiven(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/sample.xml';
        $outputDir  = $this->projectRoot . '/custom-staging';

        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath, "--output-dir={$outputDir}"]));

        self::assertFileExists($outputDir . '/posts/en/2020/2020-01-15-a-published-post.md');
    }

    public function testImportWxrFailsAndWritesNothingWhenTwoItemsCollideOnTheSameOutputPath(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/colliding.xml';
        $outputDir  = $this->projectRoot . '/custom-staging';

        self::assertSame(1, $this->app()->run(['import-wxr', $exportPath, "--output-dir={$outputDir}"]));

        // T38's own hard-failure check (ImportVerifier) runs before
        // ImportedDocumentWriter — a collision means nothing is staged at
        // all, not one of the two colliding documents silently winning.
        self::assertDirectoryDoesNotExist($outputDir);
    }

    public function testImportWxrWritesAReviewTrackingFileToTheDefaultLocation(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/sample.xml';

        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath]));

        $reviewFile = $this->projectRoot . '/var/import-review.json';
        self::assertFileExists($reviewFile);

        $contents = (string) file_get_contents($reviewFile);
        self::assertStringContainsString('"decision": "pending"', $contents);
        self::assertStringContainsString('"title": "A Published Post"', $contents);
    }

    public function testImportWxrWritesTheReviewFileToACustomPathWhenGiven(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/sample.xml';
        $reviewFile = $this->projectRoot . '/custom-review.json';

        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath, "--review-file={$reviewFile}"]));

        self::assertFileExists($reviewFile);
    }

    public function testReReimportingPreservesAPreviouslyRecordedDecision(): void
    {
        $exportPath = __DIR__ . '/../fixtures/Import/sample.xml';

        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath]));
        self::assertSame(0, $this->app()->run(['review-mark', '10', 'keep']));

        // Re-run the same import (e.g. after a fix elsewhere) — the
        // decision already recorded for source_id=10 must survive.
        self::assertSame(0, $this->app()->run(['import-wxr', $exportPath]));

        $contents = (string) file_get_contents($this->projectRoot . '/var/import-review.json');
        self::assertStringContainsString('"decision": "keep"', $contents);
    }

    public function testReviewStatusWithNoTrackingFileYetSucceedsWithNothingToShow(): void
    {
        self::assertSame(0, $this->app()->run(['review-status']));
    }

    public function testReviewStatusWithAnUnknownOptionFails(): void
    {
        self::assertSame(2, $this->app()->run(['review-status', '--bogus']));
    }

    public function testReviewStatusFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['review-status']));
    }

    public function testReviewMarkWithTooFewArgumentsFailsWithUsage(): void
    {
        self::assertSame(2, $this->app()->run(['review-mark', '10']));
    }

    public function testReviewMarkWithAnUnknownDecisionFails(): void
    {
        self::assertSame(2, $this->app()->run(['review-mark', '10', 'discard']));
    }

    public function testReviewMarkFailureFromAMissingConfigIsReportedNotFatal(): void
    {
        unlink($this->projectRoot . '/config/site.php');

        self::assertSame(1, $this->app()->run(['review-mark', '10', 'keep']));
    }

    public function testReviewMarkFailsWhenTheSourceIdIsNotInTheTrackingFile(): void
    {
        self::assertSame(0, $this->app()->run(['import-wxr', __DIR__ . '/../fixtures/Import/sample.xml']));

        self::assertSame(1, $this->app()->run(['review-mark', '999999', 'keep']));
    }

    public function testReviewMarkRecordsTheDecisionInTheTrackingFile(): void
    {
        self::assertSame(0, $this->app()->run(['import-wxr', __DIR__ . '/../fixtures/Import/sample.xml']));

        self::assertSame(0, $this->app()->run(['review-mark', '10', 'reject']));

        $contents = (string) file_get_contents($this->projectRoot . '/var/import-review.json');
        self::assertStringContainsString('"decision": "reject"', $contents);
    }

    public function testAdminCreateAccountRequiresAUsername(): void
    {
        self::assertSame(2, $this->app()->run(['admin-create-account']));
    }

    public function testAdminCreateAccountFailsForAnEmptyPasswordFile(): void
    {
        $passwordFile = $this->writePasswordFile('');

        self::assertSame(1, $this->app()->run(['admin-create-account', 'operator', "--password-file={$passwordFile}"]));
    }

    public function testAdminCreateAccountSucceedsAndWritesTheAccountStore(): void
    {
        $passwordFile = $this->writePasswordFile('a-long-enough-passphrase');

        self::assertSame(0, $this->app()->run(['admin-create-account', 'operator', "--password-file={$passwordFile}"]));

        $accountsPath = $this->projectRoot . '/var/admin/accounts.json';
        self::assertFileExists($accountsPath);
        self::assertStringContainsString('"operator"', (string) file_get_contents($accountsPath));
    }

    public function testAdminCreateAccountFailsForAnAlreadyExistingUsername(): void
    {
        $passwordFile = $this->writePasswordFile('a-long-enough-passphrase');
        self::assertSame(0, $this->app()->run(['admin-create-account', 'operator', "--password-file={$passwordFile}"]));

        self::assertSame(1, $this->app()->run(['admin-create-account', 'operator', "--password-file={$passwordFile}"]));
    }

    public function testAdminCreateAccountFailsForATooShortPassword(): void
    {
        $passwordFile = $this->writePasswordFile('short');

        self::assertSame(1, $this->app()->run(['admin-create-account', 'operator', "--password-file={$passwordFile}"]));
        self::assertFileDoesNotExist($this->projectRoot . '/var/admin/accounts.json');
    }

    private function writePasswordFile(string $password): string
    {
        $path = $this->projectRoot . '/var/password-' . uniqid() . '.txt';
        file_put_contents($path, $password . "\n");

        return $path;
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

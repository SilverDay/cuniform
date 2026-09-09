<?php

declare(strict_types=1);

namespace Cuniform\Cli;

use Cuniform\Build\BuildLock;
use Cuniform\Build\BuildOptions;
use Cuniform\Build\BuildPipeline;
use Cuniform\Build\PublicDirectorySetup;
use Cuniform\Build\ReleaseDeployer;
use Cuniform\Config\ConfigLoader;
use Cuniform\CuniformException;
use Cuniform\Cutover\LegacyUrlCrawler;
use Cuniform\Cutover\StreamHttpFetcher;
use Cuniform\Cutover\UrlInventoryWriter;

/**
 * Entry point for bin/cuniform. Stages 1-9 (SPEC §10.1) run for real, both
 * for `--dry-run` (nothing is written to disk, but Verify still runs — a
 * dry run tells you whether a real build *would* succeed) and a plain
 * build (writes a complete release tree under `paths.releases/<timestamp>/`
 * once Verify passes, then atomically deploys it into `public/`).
 * `--rollback` re-points `public/` at the release before the current one,
 * under the same build lock a build itself would hold, so a rollback and a
 * concurrent build's deploy can never race each other.
 *
 * `legacy-urls` (T25, SPEC §15.5/§19 item 5) is unrelated to the build
 * pipeline — a standalone cutover tool that crawls a live site (its
 * sitemap if it has one, a same-host spider otherwise) and writes a URL
 * inventory. Never runs as part of `build`.
 *
 * `setup-public` (T27, SPEC §10.4/§3.3) is the one-time host-provisioning
 * step: a freshly provisioned `public/` is usually a real directory (the
 * host's own placeholder), not yet the symlink `ReleaseDeployer` expects
 * to swap. Safe to run any number of times — a no-op once `public/` is
 * already a symlink.
 */
final class Application
{
    private const KNOWN_FLAGS = ['full', 'dry-run', 'rollback', 'allow-url-scheme-change'];

    /**
     * @param string $projectRoot Directory containing config/, content/, etc.
     *                            — injected rather than derived from __DIR__
     *                            so a test can point it at an isolated fixture
     *                            tree instead of this checkout's real config
     *                            and content (php-style.md: no filesystem
     *                            access outside a per-test temp directory).
     */
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param list<string> $arguments Command-line arguments, without the script name.
     */
    public function run(array $arguments): int
    {
        if ($arguments === []) {
            fwrite(STDERR, $this->usage());

            return 2;
        }

        $command = array_shift($arguments);

        if ($command === 'legacy-urls') {
            return $this->legacyUrls($arguments);
        }

        if ($command === 'setup-public') {
            return $this->setupPublic();
        }

        if ($command !== 'build') {
            fwrite(STDERR, "cuniform: unknown command '{$command}'\n" . $this->usage());

            return 2;
        }

        $flags = [];
        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--') || !in_array(substr($argument, 2), self::KNOWN_FLAGS, true)) {
                fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

                return 2;
            }

            $flags[substr($argument, 2)] = true;
        }

        if (isset($flags['rollback'])) {
            return $this->rollback();
        }

        return $this->build(isset($flags['full']), isset($flags['dry-run']), isset($flags['allow-url-scheme-change']));
    }

    /**
     * @param list<string> $arguments
     */
    private function legacyUrls(array $arguments): int
    {
        if ($arguments === [] || str_starts_with($arguments[0], '--')) {
            fwrite(STDERR, "cuniform: legacy-urls requires a base URL\n" . $this->usage());

            return 2;
        }

        $baseUrl  = array_shift($arguments);
        $output   = null;
        $maxPages = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--output=')) {
                $output = substr($argument, strlen('--output='));

                continue;
            }

            if (str_starts_with($argument, '--max-pages=')) {
                $maxPages = (int) substr($argument, strlen('--max-pages='));

                continue;
            }

            fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

            return 2;
        }

        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: legacy-urls failed\n{$e->getMessage()}\n");

            return 1;
        }

        $outputPath = $output ?? rtrim($config->paths->var, '/') . '/legacy-urls.json';
        $crawler    = $maxPages !== null
            ? new LegacyUrlCrawler(new StreamHttpFetcher(), maxPages: $maxPages)
            : new LegacyUrlCrawler(new StreamHttpFetcher());

        try {
            $inventory = $crawler->crawl($baseUrl);
            (new UrlInventoryWriter())->write($inventory, $outputPath);
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: legacy-urls failed\n{$e->getMessage()}\n");

            return 1;
        }

        $bySource = [];
        foreach ($inventory->entries as $entry) {
            $bySource[$entry->source->value] = ($bySource[$entry->source->value] ?? 0) + 1;
        }

        $breakdown = implode(', ', array_map(
            static fn (string $source, int $count): string => "{$count} via {$source}",
            array_keys($bySource),
            $bySource
        ));

        $summary = $breakdown === '' ? '' : " ({$breakdown})";
        fwrite(STDOUT, 'cuniform: found ' . count($inventory->entries) . " URLs{$summary} -> {$outputPath}\n");

        return 0;
    }

    private function setupPublic(): int
    {
        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
            $message = (new PublicDirectorySetup($config->paths->public))->run();
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: setup-public failed\n{$e->getMessage()}\n");

            return 1;
        }

        fwrite(STDOUT, "cuniform: {$message}\n");

        return 0;
    }

    private function rollback(): int
    {
        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: rollback failed\n{$e->getMessage()}\n");

            return 1;
        }

        $lock = new BuildLock(rtrim($config->paths->var, '/') . '/build.lock');

        try {
            $lock->acquire();
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: rollback failed\n{$e->getMessage()}\n");

            return 1;
        }

        try {
            $deployer = new ReleaseDeployer($config->paths->public, $config->paths->releases, $config->build->retainReleases);
            $previous = $deployer->rollback();
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: rollback failed\n{$e->getMessage()}\n");

            return 1;
        } finally {
            $lock->release();
        }

        fwrite(STDOUT, "cuniform: rolled back to {$previous}\n");

        return 0;
    }

    private function build(bool $full, bool $dryRun, bool $allowUrlSchemeChange): int
    {
        try {
            $config   = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
            $pipeline = new BuildPipeline($config, $this->projectRoot . '/config/lang');
            $result   = $pipeline->run(new BuildOptions(full: $full, dryRun: $dryRun, allowUrlSchemeChange: $allowUrlSchemeChange));
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: build failed\n{$e->getMessage()}\n");

            return 1;
        }

        foreach ($result->warnings as $warning) {
            fwrite(STDERR, "cuniform: warning: {$warning}\n");
        }

        $reused = $result->reusedDocumentCount > 0 ? " ({$result->reusedDocumentCount} reused from cache)" : '';

        if ($result->releaseDir === null) {
            fwrite(STDOUT, "cuniform: dry run OK — {$result->documentCount} documents{$reused}, {$result->routeCount} routes\n");

            return 0;
        }

        fwrite(STDOUT, "cuniform: built {$result->documentCount} documents{$reused}, {$result->routeCount} routes -> {$result->releaseDir}\n");
        fwrite(STDOUT, "cuniform: deployed -> {$this->projectRoot}/public\n");

        return 0;
    }

    private function usage(): string
    {
        return <<<'TXT'
        Usage:
          cuniform build [--full] [--dry-run] [--allow-url-scheme-change]
          cuniform build --rollback
          cuniform legacy-urls <base-url> [--output=<path>] [--max-pages=<n>]
          cuniform setup-public

        TXT;
    }
}

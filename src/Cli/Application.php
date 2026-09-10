<?php

declare(strict_types=1);

namespace Cuniform\Cli;

use Cuniform\Admin\Auth\AccountEnrollment;
use Cuniform\Admin\Auth\AdminAccountStore;
use Cuniform\Build\BuildException;
use Cuniform\Build\BuildLock;
use Cuniform\Build\BuildLogEntry;
use Cuniform\Build\BuildLogWriter;
use Cuniform\Build\BuildOptions;
use Cuniform\Build\BuildOutcome;
use Cuniform\Build\BuildPipeline;
use Cuniform\Build\PublicDirectorySetup;
use Cuniform\Build\ReleaseDeployer;
use Cuniform\Config\Config;
use Cuniform\Config\ConfigLoader;
use Cuniform\CuniformException;
use Cuniform\Cutover\LegacyUrlCrawler;
use Cuniform\Cutover\StreamHttpFetcher;
use Cuniform\Cutover\UrlInventoryWriter;
use Cuniform\Import\ImportedDocumentWriter;
use Cuniform\Import\ImportReport;
use Cuniform\Import\ImportVerifier;
use Cuniform\Import\ReviewChecklistBuilder;
use Cuniform\Import\ReviewChecklistStore;
use Cuniform\Import\ReviewDecision;
use Cuniform\Import\ReviewEntry;
use Cuniform\Import\WxrImporter;
use Cuniform\Import\WxrReader;

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
 *
 * `import-wxr` (T34-38, SPEC Appendix A) reads a WordPress export and
 * writes candidate Cuniform documents to a staging directory
 * (`var/import/` by default) — never into `content/` directly. SPEC
 * §A.5's manual review happens between "imported" and "shipped"; this
 * command only does the first half. `ImportVerifier` (T38, SPEC §A.4)
 * runs before anything is written — a hard failure there (e.g. two items
 * colliding on the same output path) means nothing is staged at all,
 * rather than half an import landing on disk. Every run also builds and
 * saves the T39 review tracking file (`var/import-review.json` by
 * default), merging in any decisions already recorded so a re-import
 * (e.g. after fixing an unknown shortcode) never discards review work
 * already done.
 *
 * `review-status`/`review-mark` (T39, SPEC §A.5) work the tracking file
 * on its own, without touching the WXR export or the staged documents —
 * the actual review-and-decide workflow, run as many times as needed
 * across as many sessions as it takes ("an interrupted review can resume
 * rather than restart").
 *
 * `admin-create-account` (T28, SPEC §13.1) bootstraps the (single, for
 * now — P2 has no self-registration) admin operator account: password,
 * TOTP secret, and ten recovery codes, all generated here and shown
 * exactly once. The password is read from `--password-file=<path>` (a
 * scriptable, testable alternative to an interactive masked prompt — see
 * this method's own docblock) or, failing that, one line from STDIN;
 * never from an argument, which `ps`/shell history would expose.
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

        if ($command === 'import-wxr') {
            return $this->importWxr($arguments);
        }

        if ($command === 'review-status') {
            return $this->reviewStatus($arguments);
        }

        if ($command === 'review-mark') {
            return $this->reviewMark($arguments);
        }

        if ($command === 'admin-create-account') {
            return $this->adminCreateAccount($arguments);
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

    /**
     * @param list<string> $arguments
     */
    private function importWxr(array $arguments): int
    {
        if ($arguments === [] || str_starts_with($arguments[0], '--')) {
            fwrite(STDERR, "cuniform: import-wxr requires a path to a WXR export\n" . $this->usage());

            return 2;
        }

        $path       = array_shift($arguments);
        $outputDir  = null;
        $reviewFile = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--output-dir=')) {
                $outputDir = substr($argument, strlen('--output-dir='));

                continue;
            }

            if (str_starts_with($argument, '--review-file=')) {
                $reviewFile = substr($argument, strlen('--review-file='));

                continue;
            }

            fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

            return 2;
        }

        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: import-wxr failed\n{$e->getMessage()}\n");

            return 1;
        }

        $outputDir  ??= rtrim($config->paths->var, '/') . '/import';
        $reviewFile ??= rtrim($config->paths->var, '/') . '/import-review.json';

        try {
            $wxr    = (new WxrReader())->parse($path);
            $result = (new WxrImporter($config->defaultLanguage))->import($wxr);
            $report = (new ImportVerifier())->verify($wxr, $result);
            (new ImportedDocumentWriter())->write($result['documents'], $outputDir);

            $store   = new ReviewChecklistStore();
            $builder = new ReviewChecklistBuilder();
            $checklist = $builder->merge($builder->build($wxr, $result, $report), $store->load($reviewFile));
            $store->save($reviewFile, $checklist);
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: import-wxr failed\n{$e->getMessage()}\n");

            return 1;
        }

        foreach ($result['warnings'] as $warning) {
            fwrite(STDERR, "cuniform: warning: {$warning}\n");
        }

        fwrite(STDOUT, 'cuniform: imported ' . count($result['documents']) . " documents -> {$outputDir}\n");
        fwrite(STDOUT, "cuniform: staged for review (SPEC §A.5) — not written to content/. "
            . "Review each document, then copy the ones you keep into content/posts/{$config->defaultLanguage}/.\n");

        $this->printMigrationReport($report);
        $this->printReviewSummary($checklist, $reviewFile);

        return 0;
    }

    /**
     * @param array<int|string, ReviewEntry> $checklist
     */
    private function printReviewSummary(array $checklist, string $reviewFile): void
    {
        $pending = 0;
        $kept    = 0;
        $rejected = 0;

        foreach ($checklist as $entry) {
            match ($entry->decision) {
                ReviewDecision::Pending => $pending++,
                ReviewDecision::Keep => $kept++,
                ReviewDecision::Reject => $rejected++,
            };
        }

        fwrite(STDOUT, "cuniform: review checklist -> {$reviewFile} ({$pending} pending, {$kept} kept, "
            . "{$rejected} rejected) — see 'cuniform review-status' (SPEC §A.5)\n");
    }

    /**
     * SPEC §A.4's migration report. Items already surfaced above as
     * `cuniform: warning: ...` lines (everything `WxrImporter` itself
     * produced) aren't repeated here — only what `ImportVerifier` finds
     * that the importer's own warnings don't already cover: the
     * per-status tally, unresolved legacy URLs, and word-count outliers.
     */
    private function printMigrationReport(ImportReport $report): void
    {
        $statusCounts = implode(', ', array_map(
            static fn (string $status, int $count): string => "{$status}={$count}",
            array_keys($report->countsByStatus),
            $report->countsByStatus,
        ));
        fwrite(STDOUT, "cuniform: {$report->totalItems} WXR items, {$report->importedCount} imported"
            . ($statusCounts === '' ? '' : " ({$statusCounts})") . "\n");

        foreach ($report->unresolvedLegacyUrls as $unresolved) {
            fwrite(STDERR, "cuniform: warning: {$unresolved}\n");
        }

        foreach ($report->wordCountOutliers as $outlier) {
            fwrite(STDERR, "cuniform: warning: {$outlier}\n");
        }

        fwrite(STDOUT, sprintf(
            'cuniform: report: %d unknown shortcode(s), %d unsupported construct(s), '
                . "%d item(s) needing a manual decision, %d other notice(s)\n",
            count($report->unknownShortcodes),
            count($report->unsupportedConstructs),
            count($report->manualDecisionItems),
            count($report->otherNotices),
        ));

        fwrite(STDOUT, $report->isClean()
            ? "cuniform: report is clean (SPEC §A.4) — nothing left here to review\n"
            : "cuniform: report is not clean (SPEC §A.4) — re-run once the items above are addressed\n");
    }

    /**
     * @param list<string> $arguments
     */
    private function reviewStatus(array $arguments): int
    {
        $file = null;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--file=')) {
                $file = substr($argument, strlen('--file='));

                continue;
            }

            fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

            return 2;
        }

        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: review-status failed\n{$e->getMessage()}\n");

            return 1;
        }

        $file ??= rtrim($config->paths->var, '/') . '/import-review.json';

        try {
            $checklist = (new ReviewChecklistStore())->load($file);
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: review-status failed\n{$e->getMessage()}\n");

            return 1;
        }

        if ($checklist === []) {
            fwrite(STDOUT, "cuniform: no review tracking file yet ({$file}) — run 'cuniform import-wxr' first\n");

            return 0;
        }

        fwrite(STDOUT, "cuniform: SPEC §A.5 review checklist — confirm, per document:\n");
        fwrite(STDOUT, "  1. no shortcode with an attacker-influenced attribute (src, href, ...)\n");
        fwrite(STDOUT, "  2. every external link points where the text claims\n");
        fwrite(STDOUT, "  3. no leftover verbatim [shortcode] from a plugin that no longer exists\n");
        fwrite(STDOUT, "  4. no embedded tracking pixel or third-party asset surviving as an image\n");
        fwrite(STDOUT, "  5. content genuinely worth keeping\n\n");

        $ordered = (new ReviewChecklistBuilder())->sortedForReview($checklist);

        $pending = array_filter($ordered, static fn (ReviewEntry $entry): bool => $entry->decision === ReviewDecision::Pending);
        $decided = count($ordered) - count($pending);

        fwrite(STDOUT, 'cuniform: ' . count($pending) . ' pending, ' . $decided . " already decided -> {$file}\n");

        foreach ($pending as $entry) {
            $flagNote = $entry->flags === [] ? '' : ' [' . count($entry->flags) . ' flag(s) from the migration report]';
            fwrite(STDOUT, "  source_id={$entry->sourceId} {$entry->relativePath}{$flagNote}\n");
            fwrite(STDOUT, "    {$entry->title}\n");
            foreach ($entry->flags as $flag) {
                fwrite(STDOUT, "    - {$flag}\n");
            }
        }

        return 0;
    }

    /**
     * @param list<string> $arguments
     */
    private function reviewMark(array $arguments): int
    {
        if (count($arguments) < 2 || str_starts_with($arguments[0], '--') || str_starts_with($arguments[1], '--')) {
            fwrite(STDERR, "cuniform: review-mark requires a source_id and a decision\n" . $this->usage());

            return 2;
        }

        $sourceId       = array_shift($arguments);
        $decisionArgument = array_shift($arguments);
        $file           = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--file=')) {
                $file = substr($argument, strlen('--file='));

                continue;
            }

            fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

            return 2;
        }

        $decision = ReviewDecision::tryFrom($decisionArgument);
        if ($decision === null) {
            fwrite(STDERR, "cuniform: unknown decision '{$decisionArgument}' (expected pending, keep, or reject)\n" . $this->usage());

            return 2;
        }

        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: review-mark failed\n{$e->getMessage()}\n");

            return 1;
        }

        $file ??= rtrim($config->paths->var, '/') . '/import-review.json';

        try {
            $store     = new ReviewChecklistStore();
            $checklist = $store->load($file);

            if (!isset($checklist[$sourceId])) {
                fwrite(STDERR, "cuniform: review-mark failed\nsource_id '{$sourceId}' is not in {$file} "
                    . "— run 'cuniform import-wxr' first\n");

                return 1;
            }

            $checklist[$sourceId] = $checklist[$sourceId]->withDecision($decision);
            $store->save($file, $checklist);
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: review-mark failed\n{$e->getMessage()}\n");

            return 1;
        }

        fwrite(STDOUT, "cuniform: source_id={$sourceId} marked '{$decision->value}' -> {$file}\n");

        return 0;
    }

    /**
     * @param list<string> $arguments
     */
    private function adminCreateAccount(array $arguments): int
    {
        if ($arguments === [] || str_starts_with($arguments[0], '--')) {
            fwrite(STDERR, "cuniform: admin-create-account requires a username\n" . $this->usage());

            return 2;
        }

        $username     = array_shift($arguments);
        $passwordFile = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--password-file=')) {
                $passwordFile = substr($argument, strlen('--password-file='));

                continue;
            }

            fwrite(STDERR, "cuniform: unknown option '{$argument}'\n" . $this->usage());

            return 2;
        }

        try {
            $config = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: admin-create-account failed\n{$e->getMessage()}\n");

            return 1;
        }

        $password = $this->readPassword($passwordFile);
        if ($password === '') {
            fwrite(STDERR, "cuniform: admin-create-account failed\nno password given "
                . "(--password-file=<path>, or pipe one line on STDIN)\n");

            return 1;
        }

        $accountsPath = rtrim($config->paths->var, '/') . '/admin/accounts.json';

        try {
            $enrollment = (new AccountEnrollment(new AdminAccountStore($accountsPath), issuer: $config->title))
                ->enroll($username, $password);
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: admin-create-account failed\n{$e->getMessage()}\n");

            return 1;
        }

        fwrite(STDOUT, "cuniform: admin account '{$username}' created -> {$accountsPath}\n\n");
        fwrite(STDOUT, "TOTP secret (base32, enter manually if you can't scan a QR code):\n  {$enrollment->totpSecretBase32}\n\n");
        fwrite(STDOUT, "TOTP provisioning URI (feed this to any QR code generator):\n  {$enrollment->totpProvisioningUri}\n\n");
        fwrite(STDOUT, "Recovery codes (SPEC §13.1 — single-use, shown once, store them somewhere safe):\n");
        foreach ($enrollment->recoveryCodes as $code) {
            fwrite(STDOUT, "  {$code}\n");
        }
        fwrite(STDOUT, "\ncuniform: none of the above is recoverable — it is not stored in plaintext anywhere.\n");

        return 0;
    }

    /**
     * `--password-file=<path>` is the primary, testable path (a real
     * interactive masked prompt can't be exercised by a same-process
     * PHPUnit run — the same "thin and untested directly" boundary
     * StreamHttpFetcher already draws around real I/O, T25's own note).
     * Without it, one line is read from STDIN — usable non-interactively
     * (`echo "$PW" | cuniform admin-create-account ...`) without ever
     * putting the password in `$argv`, which `ps`/shell history would
     * otherwise expose.
     */
    private function readPassword(?string $passwordFile): string
    {
        if ($passwordFile !== null) {
            $contents = is_file($passwordFile) ? file_get_contents($passwordFile) : false;

            return $contents === false ? '' : trim($contents);
        }

        $line = fgets(STDIN);

        return $line === false ? '' : trim($line);
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
        $startedAt = microtime(true);
        $config    = null;

        try {
            $config   = (new ConfigLoader())->load($this->projectRoot . '/config/site.php');
            $pipeline = new BuildPipeline($config, $this->projectRoot . '/config/lang');
            $result   = $pipeline->run(new BuildOptions(full: $full, dryRun: $dryRun, allowUrlSchemeChange: $allowUrlSchemeChange));
        } catch (CuniformException $e) {
            fwrite(STDERR, "cuniform: build failed\n{$e->getMessage()}\n");

            if (!$dryRun) {
                $this->recordBuildLog($config, $startedAt, BuildOutcome::Failed, [], 0, null, $e->getMessage());
            }

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

        $this->recordBuildLog($config, $startedAt, BuildOutcome::Success, $result->documentCountByLanguage, $result->routeCount, $result->releaseDir, null);

        return 0;
    }

    /**
     * SPEC §15.4's build log — written from here, the one point every real
     * trigger (git push's `post-receive`, the admin-enqueue `.path` unit,
     * the scheduled-post `.timer`, and a manual `bin/cuniform build`)
     * converges on, rather than from BuildPipeline itself (see
     * BuildLogWriter's own docblock). Never called for `--dry-run`: nothing
     * was deployed, so there's no outcome for a "last build status"
     * dashboard reading to reflect. `$config` is null only when
     * ConfigLoader itself failed — there is then no `paths.var` to write a
     * log into, so logging is silently skipped rather than attempted
     * against a path this process doesn't actually know.
     *
     * @param array<string, int> $documentCountByLanguage
     */
    private function recordBuildLog(
        ?Config $config,
        float $startedAt,
        BuildOutcome $outcome,
        array $documentCountByLanguage,
        int $routeCount,
        ?string $releaseDir,
        ?string $message,
    ): void {
        if ($config === null) {
            return;
        }

        $writer = new BuildLogWriter(rtrim($config->paths->var, '/') . '/log/build.jsonl');
        $entry  = new BuildLogEntry(new \DateTimeImmutable(), $outcome, microtime(true) - $startedAt, $documentCountByLanguage, $routeCount, $releaseDir, $message);

        try {
            $writer->record($entry);
        } catch (BuildException $e) {
            // The build's own real outcome is already reported via
            // stdout/stderr and this method's caller's return value — a
            // second failure writing *about* that outcome has nothing
            // more actionable to do than note it and move on.
            fwrite(STDERR, "cuniform: warning: could not write the build log: {$e->getMessage()}\n");
        }
    }

    private function usage(): string
    {
        return <<<'TXT'
        Usage:
          cuniform build [--full] [--dry-run] [--allow-url-scheme-change]
          cuniform build --rollback
          cuniform legacy-urls <base-url> [--output=<path>] [--max-pages=<n>]
          cuniform setup-public
          cuniform import-wxr <path-to-export.xml> [--output-dir=<path>] [--review-file=<path>]
          cuniform review-status [--file=<path>]
          cuniform review-mark <source-id> <pending|keep|reject> [--file=<path>]
          cuniform admin-create-account <username> [--password-file=<path>]

        TXT;
    }
}

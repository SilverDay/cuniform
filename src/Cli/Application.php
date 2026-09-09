<?php

declare(strict_types=1);

namespace Cuniform\Cli;

use Cuniform\Build\BuildOptions;
use Cuniform\Build\BuildPipeline;
use Cuniform\Config\ConfigLoader;
use Cuniform\CuniformException;

/**
 * Entry point for bin/cuniform. Stages 1-8 (SPEC §10.1) run for real, both
 * for `--dry-run` (nothing is written to disk, but Verify still runs — a
 * dry run tells you whether a real build *would* succeed) and a plain
 * build (writes a complete release tree under `paths.releases/<timestamp>/`
 * once Verify passes). Stage 9 — the atomic deploy into `public/` — is
 * T23 and doesn't exist yet, so a plain build never touches `public/`, and
 * `--rollback` (which presupposes a deploy to roll back from) stays
 * unimplemented until T23.
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
            fwrite(STDOUT, "cuniform: rollback is not implemented yet (see docs/BUILD-ORDER.md, T23)\n");

            return 1;
        }

        return $this->build(isset($flags['full']), isset($flags['dry-run']), isset($flags['allow-url-scheme-change']));
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

        if ($result->releaseDir === null) {
            fwrite(STDOUT, "cuniform: dry run OK — {$result->documentCount} documents, {$result->routeCount} routes\n");

            return 0;
        }

        fwrite(STDOUT, "cuniform: built {$result->documentCount} documents, {$result->routeCount} routes -> {$result->releaseDir}\n");
        fwrite(STDOUT, "cuniform: deploy is not implemented yet (see docs/BUILD-ORDER.md, T23) — public/ was not updated\n");

        return 0;
    }

    private function usage(): string
    {
        return <<<'TXT'
        Usage:
          cuniform build [--full] [--dry-run] [--allow-url-scheme-change]
          cuniform build --rollback

        TXT;
    }
}

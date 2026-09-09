<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * What a completed run of stages 1-6 produced. `releaseDir` is null for a
 * `--dry-run` build (SPEC §10.5) — stages 1-6 run in full, including
 * rendering every template to a string, but nothing is written to disk.
 * Deploying `releaseDir` into `public/` (the atomic swap, SPEC §10.4) is
 * T23 and does not happen here.
 */
final class BuildResult
{
    /**
     * @param list<string> $warnings Non-fatal (e.g. a nav parent directory with no
     *                                index.md, SPEC §6.3) — a successful build can
     *                                still have warnings.
     */
    public function __construct(
        public readonly int $documentCount,
        public readonly int $routeCount,
        public readonly array $warnings,
        public readonly ?string $releaseDir,
    ) {
    }
}

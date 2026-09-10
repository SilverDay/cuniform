<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * What a completed build produced. `releaseDir` is null for a `--dry-run`
 * build (SPEC §10.5) — every stage still runs in full, including
 * rendering every template to a string and Verify, but nothing is
 * written to disk and Deploy never runs.
 */
final class BuildResult
{
    /**
     * @param list<string>        $warnings                Non-fatal (e.g. a nav parent
     *                                                       directory with no index.md,
     *                                                       SPEC §6.3) — a successful
     *                                                       build can still have warnings.
     * @param array<string, int>  $documentCountByLanguage SPEC §15.4's build log needs
     *                                                       this broken out per language,
     *                                                       not just the aggregate
     *                                                       $documentCount.
     */
    public function __construct(
        public readonly int $documentCount,
        public readonly int $routeCount,
        public readonly array $warnings,
        public readonly ?string $releaseDir,
        public readonly int $reusedDocumentCount = 0,
        public readonly array $documentCountByLanguage = [],
    ) {
    }
}

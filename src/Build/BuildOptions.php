<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * CLI flags that reach the pipeline (SPEC §10.5, §10.3, §10.2,
 * `bin/cuniform build [--full] [--dry-run] [--allow-url-scheme-change]`).
 * `full` forces every document through IncrementalPlanner as dirty,
 * bypassing the build cache entirely (SPEC §10.2) — the explicit escape
 * hatch for cases the cache can't see on its own, e.g. an edited
 * `[include]` target (see IncrementalPlanner's own docblock).
 * `allowUrlSchemeChange` is the explicit statement of intent §10.3 requires
 * before a build is allowed to silently move every URL on the site.
 */
final class BuildOptions
{
    public function __construct(
        public readonly bool $full = false,
        public readonly bool $dryRun = false,
        public readonly bool $allowUrlSchemeChange = false,
    ) {
    }
}

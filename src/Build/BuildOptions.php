<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * CLI flags that reach the pipeline (SPEC §10.5, `bin/cuniform build [--full] [--dry-run]`).
 * `full` is accepted but has no effect yet — incremental builds are T24; every
 * build is a full build until that lands, which is also T24's own "any
 * ambiguity resolves toward a full rebuild" default (SPEC §10.2).
 */
final class BuildOptions
{
    public function __construct(
        public readonly bool $full = false,
        public readonly bool $dryRun = false,
    ) {
    }
}

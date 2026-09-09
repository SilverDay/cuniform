<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * One WXR item that became a real Cuniform document (SPEC §A.3) — ready
 * to write to disk, though `WxrImporter` itself never writes anything
 * (SPEC §A.5: imported content is reviewed before it ships; a class this
 * generic-purpose shouldn't decide where "before it ships" review happens
 * — see the CLI command for where these actually land).
 */
final class ImportedDocument
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $contents,
        public readonly string $sourceId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Writes `WxrImporter`'s output to disk — deliberately not into
 * `content/` directly. SPEC §A.5: "Every imported document is reviewed
 * before it ships... the control that keeps the single-origin
 * architecture sound." Writing straight into `content/posts/` would make
 * a freshly imported document visible to the very next `bin/cuniform
 * build` with no review step in between; landing it in a staging
 * directory instead (`var/import/` by default — never built, never
 * served) means promoting a document into `content/` is a distinct,
 * deliberate, reviewed action the operator takes themselves.
 */
final class ImportedDocumentWriter
{
    /**
     * @param list<ImportedDocument> $documents
     *
     * @throws ImportException When a file can't be written.
     */
    public function write(array $documents, string $outputDir): void
    {
        foreach ($documents as $document) {
            $target = rtrim($outputDir, '/') . '/' . $document->relativePath;
            $dir    = dirname($target);

            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw ImportException::fromErrors(["could not create directory: {$dir}"]);
            }

            if (file_put_contents($target, $document->contents) === false) {
                throw ImportException::fromErrors(["could not write: {$target}"]);
            }
        }
    }
}

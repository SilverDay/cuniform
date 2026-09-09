<?php

declare(strict_types=1);

namespace Cuniform\Cutover;

/**
 * Writes a UrlInventory to disk as JSON — the practical hand-off point
 * to whatever comes next in the cutover (T26's frozen-snapshot support,
 * or manual curation of `content/redirects.map`, SPEC §8.3). Not part of
 * a release, not read by the build pipeline itself.
 */
final class UrlInventoryWriter
{
    /**
     * @throws CutoverException When the target directory can't be created
     *                          or the file can't be written.
     */
    public function write(UrlInventory $inventory, string $path): void
    {
        $entries = array_map(static fn (LegacyUrlEntry $entry): array => [
            'path'        => $entry->path,
            'statusCode'  => $entry->statusCode,
            'contentType' => $entry->contentType,
            'source'      => $entry->source->value,
        ], $inventory->entries);

        $json = json_encode(
            ['baseUrl' => $inventory->baseUrl, 'count' => count($entries), 'entries' => $entries],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR
        );

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw CutoverException::fromErrors(["could not create directory: {$dir}"]);
        }

        if (file_put_contents($path, $json) === false) {
            throw CutoverException::fromErrors(["could not write: {$path}"]);
        }
    }
}

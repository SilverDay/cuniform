<?php

declare(strict_types=1);

namespace Cuniform\Build;

/**
 * Copies `content/media/` into the release tree (SPEC §7.10: the media
 * tree is "single, shared across languages" — the same category as
 * `sitemap.xml`/`search-index.json`, which T20 already emits). No earlier
 * task copied media at all; without it, nothing referencing an image ever
 * actually had a file behind it in the release, and the missing-media-file
 * check §10.3 requires would have nothing to check against.
 *
 * Deliberately not loaded through the `ArtifactFile` (in-memory content)
 * pattern the way HTML pages and other artifacts are: a media tree can be
 * many megabytes, and holding every file's bytes in memory at once — just
 * to satisfy the "nothing written until everything succeeds" guarantee
 * template rendering needs (SPEC §9 rule 6) — isn't a trade worth making
 * here. `list()` only reads filenames (cheap, needed by the Verify stage
 * to know what *will* exist); `copyInto()` streams bytes directly to the
 * release directory during the write phase, same as any other file copy.
 */
final class MediaCopier
{
    public function __construct(private readonly string $contentRoot)
    {
    }

    /**
     * Release-relative paths, e.g. "media/2026/03/photo.jpg" — what the
     * Verify stage needs to know resolves, without reading any file's bytes.
     *
     * @return list<string>
     */
    public function list(): array
    {
        $root = rtrim($this->contentRoot, '/') . '/media';
        if (!is_dir($root)) {
            return [];
        }

        $paths    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            if (!$fileInfo->isFile()) {
                continue;
            }

            $paths[] = 'media/' . ltrim(substr($fileInfo->getPathname(), strlen($root)), '/');
        }

        sort($paths);

        return $paths;
    }

    /**
     * @throws BuildException When a file can't be read or the target can't be written.
     */
    public function copyInto(string $releaseDir): void
    {
        $root = rtrim($this->contentRoot, '/') . '/media';
        if (!is_dir($root)) {
            return;
        }

        foreach ($this->list() as $relativePath) {
            $source = rtrim($this->contentRoot, '/') . '/' . $relativePath;
            $target = rtrim($releaseDir, '/') . '/' . $relativePath;

            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
                throw BuildException::fromErrors(["could not create directory: {$targetDir}"]);
            }

            if (!copy($source, $target)) {
                throw BuildException::fromErrors(["could not copy media file: {$source}"]);
            }
        }
    }
}

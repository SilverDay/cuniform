<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * Read-only listing of `content/media/` (SPEC §13.3: "media library").
 * Mirrors Build\MediaCopier's own tree walk rather than reusing it
 * directly — MediaCopier returns bare release-relative paths for the build
 * pipeline's own purposes, while this needs per-file metadata (size,
 * dimensions, modified time) for an operator-facing listing, and the two
 * have no consumer in common that would justify sharing more than the
 * traversal shape.
 *
 * A file whose dimensions can't be read (present on disk but not a decodable
 * image — outside anything MediaUploader itself could have produced, but
 * content/media/ is edited outside this application too, e.g. by hand or by
 * P3's importer) is listed with width/height 0 rather than dropped — same
 * "don't let one bad entry take down the whole listing" rule
 * DocumentIndex::summaries() already follows for content documents.
 */
final class MediaLibrary
{
    public function __construct(private readonly string $contentRoot)
    {
    }

    /**
     * Newest first — the files an operator most likely just uploaded.
     *
     * @return list<MediaLibraryEntry>
     */
    public function list(): array
    {
        $root = rtrim($this->contentRoot, '/') . '/media';
        if (!is_dir($root)) {
            return [];
        }

        $entries  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePath = ltrim(substr($absolutePath, strlen($root)), '/');

            $size = filesize($absolutePath);
            $mtime = filemtime($absolutePath);

            $dimensions = @getimagesize($absolutePath);
            [$width, $height] = $dimensions !== false ? [$dimensions[0], $dimensions[1]] : [0, 0];

            $entries[] = new MediaLibraryEntry(
                $relativePath,
                '/media/' . $relativePath,
                $size === false ? 0 : $size,
                $width,
                $height,
                new \DateTimeImmutable('@' . ($mtime === false ? 0 : $mtime)),
            );
        }

        usort($entries, static fn (MediaLibraryEntry $a, MediaLibraryEntry $b): int => $b->modifiedAt <=> $a->modifiedAt);

        return $entries;
    }
}

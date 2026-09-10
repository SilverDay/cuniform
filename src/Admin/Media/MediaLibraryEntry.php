<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * One row of MediaLibrary::list() — an already-stored file, discovered by
 * scanning disk rather than read from any metadata store (there is no
 * database, CLAUDE.md: "No runtime dependencies ... no database").
 */
final class MediaLibraryEntry
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $url,
        public readonly int $bytes,
        public readonly int $width,
        public readonly int $height,
        public readonly \DateTimeImmutable $modifiedAt,
    ) {
    }
}

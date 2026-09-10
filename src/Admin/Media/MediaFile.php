<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * One file stored under `content/media/` — the result of a successful
 * upload, and one row of MediaLibrary::list(). $relativePath is relative to
 * `content/media/` (e.g. "2026/03/<random>.jpg"); $url is the path a
 * document's front matter `image` key, or a `[figure src=...]` shortcode,
 * would reference (SPEC §5.1: media is "language-neutral, shared").
 */
final class MediaFile
{
    public function __construct(
        public readonly string $relativePath,
        public readonly string $url,
        public readonly int $bytes,
        public readonly int $width,
        public readonly int $height,
    ) {
    }
}

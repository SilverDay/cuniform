<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * MediaReencoder's output: freshly-encoded image bytes (no EXIF, no
 * embedded payload beyond pixel data — SPEC §13.2) plus the dimensions GD
 * already had in hand while decoding, so nothing downstream has to decode
 * the image a second time just to learn its width/height.
 */
final class ReencodedImage
{
    public function __construct(
        public readonly string $bytes,
        public readonly int $width,
        public readonly int $height,
    ) {
    }
}

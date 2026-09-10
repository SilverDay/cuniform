<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * The "magic bytes" check SPEC §13.2 names alongside extension and
 * content-type — the only one of the three that reads the file's actual
 * contents rather than trusting something the client sent. Hand-rolled
 * rather than ext-exif's exif_imagetype(), so this doesn't add an implicit
 * extension requirement beyond GD (already required to re-encode).
 */
final class MediaMagicBytes
{
    /**
     * @param string $header At least the first 12 bytes of the file — every
     *                       signature below fits within that.
     */
    public static function detect(string $header): ?MediaFormat
    {
        if (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
            return MediaFormat::Jpeg;
        }

        if (strncmp($header, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) {
            return MediaFormat::Png;
        }

        if (strlen($header) >= 12 && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return MediaFormat::Webp;
        }

        return null;
    }
}

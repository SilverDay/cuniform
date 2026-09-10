<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

use Cuniform\Admin\AdminException;

/**
 * Re-encodes an uploaded image through GD (SPEC §13.2: "re-encoded through
 * GD/Imagick to strip EXIF and any embedded payload"). Decoding to a plain
 * in-memory bitmap and re-encoding it drops EXIF, ICC profiles, XMP, and
 * any other non-pixel data by construction — GD never round-trips metadata
 * it didn't ask for — and it turns any payload smuggled into the file
 * outside the pixel data (a polyglot, a trailing chunk after the image
 * data) into nothing, since only the decoded pixels ever reach the output.
 *
 * No `@` (php-style.md): GD's imagecreatefrom*() functions emit an
 * E_WARNING and return false on a malformed file rather than throwing, so a
 * temporary error handler converts that warning into an AdminException
 * instead of suppressing it.
 */
final class MediaReencoder
{
    private const JPEG_QUALITY = 85;
    private const PNG_COMPRESSION = 6;
    private const WEBP_QUALITY = 85;

    public function reencode(string $sourcePath, MediaFormat $format): ReencodedImage
    {
        $this->assertEngineAvailable($format);

        $image = $this->decode($sourcePath, $format);

        try {
            $width  = imagesx($image);
            $height = imagesy($image);
            $bytes  = $this->encode($image, $format);
        } finally {
            imagedestroy($image);
        }

        return new ReencodedImage($bytes, $width, $height);
    }

    /**
     * No variable function calls (php-style.md) — GD's decoder for each
     * format is named explicitly in this match rather than looked up by a
     * string built from $format and invoked dynamically.
     */
    private function assertEngineAvailable(MediaFormat $format): void
    {
        if (!extension_loaded('gd')) {
            throw AdminException::mediaEngineUnavailable('ext-gd is not loaded');
        }

        $available = match ($format) {
            MediaFormat::Jpeg => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            MediaFormat::Png => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            MediaFormat::Webp => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
        };

        if (!$available) {
            throw AdminException::mediaEngineUnavailable("GD was not built with {$format->value} support");
        }
    }

    private function decode(string $sourcePath, MediaFormat $format): \GdImage
    {
        set_error_handler(static function (int $errno, string $errstr) use ($format): bool {
            throw AdminException::mediaDecodeFailed("{$format->value}: {$errstr}");
        });

        try {
            $image = match ($format) {
                MediaFormat::Jpeg => imagecreatefromjpeg($sourcePath),
                MediaFormat::Png => imagecreatefrompng($sourcePath),
                MediaFormat::Webp => imagecreatefromwebp($sourcePath),
            };
        } finally {
            restore_error_handler();
        }

        if (!$image instanceof \GdImage) {
            throw AdminException::mediaDecodeFailed("{$format->value}: decoder returned no image");
        }

        return $image;
    }

    private function encode(\GdImage $image, MediaFormat $format): string
    {
        if ($format === MediaFormat::Png || $format === MediaFormat::Webp) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        $written = match ($format) {
            MediaFormat::Jpeg => imagejpeg($image, null, self::JPEG_QUALITY),
            MediaFormat::Png => imagepng($image, null, self::PNG_COMPRESSION),
            MediaFormat::Webp => imagewebp($image, null, self::WEBP_QUALITY),
        };
        $bytes = ob_get_clean();

        if (!$written || $bytes === false) {
            throw AdminException::mediaDecodeFailed("{$format->value}: re-encoding failed");
        }

        return $bytes;
    }
}

<?php

declare(strict_types=1);

namespace Cuniform\Admin\Media;

/**
 * The image formats the media library accepts (SPEC §13.2: "media upload
 * validated on extension and content type and magic bytes, re-encoded
 * through GD/Imagick"). SPEC names no format list — this is a scope
 * decision, documented here rather than in a silent default:
 *
 * - JPEG, PNG, WebP: the three formats GD can both decode and re-encode
 *   without losing anything a reader would notice.
 * - GIF is deliberately excluded: GD's imagecreatefromgif() only reads the
 *   first frame, so re-encoding an animated GIF through this pipeline would
 *   silently flatten it to a static image — exactly the kind of surprising
 *   data loss this project's conventions avoid inflicting unasked. Revisit
 *   if animated-GIF support is actually needed.
 * - SVG is deliberately excluded: it is XML, not raster pixel data, so
 *   "re-encode to strip embedded payloads" has no meaning for it, and an
 *   SVG can carry `<script>`/event-handler content that executes when a
 *   browser navigates to it directly — served same-origin as admin
 *   (SPEC §3.4), that is exactly the XSS class this project's security
 *   posture (security.md: "no unsafe-inline ... no user-submitted content
 *   of any kind") exists to keep out.
 */
enum MediaFormat: string
{
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Webp = 'webp';

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Jpeg => ['jpg', 'jpeg'],
            self::Png => ['png'],
            self::Webp => ['webp'],
        };
    }

    public function primaryExtension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Webp => 'webp',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower($extension);

        foreach (self::cases() as $case) {
            if (in_array($extension, $case->extensions(), true)) {
                return $case;
            }
        }

        return null;
    }

    public static function fromMimeType(string $mimeType): ?self
    {
        $mimeType = strtolower(trim($mimeType));

        foreach (self::cases() as $case) {
            if ($mimeType === $case->mimeType()) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return array_merge(...array_map(static fn (self $case): array => $case->extensions(), self::cases()));
    }
}
